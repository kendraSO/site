<?php

require_once 'Site/dataobjects/SiteMedia.php';

/**
 * An audio specific media object
 *
 * @package   Site
 * @copyright 2011-2016 silverorange
 */
class SiteAudioMedia extends SiteMedia
{
	// {{{ class constants

	/**
	 * Starting offset in seconds to look for pts_time packets with ffprobe
	 *
	 * If this is greater than the duration of the stream ffprobe just seeks to
	 * the end of the stream.
	 */
	const FFPROBE_DEFAULT_OFFSET = 432000; // 12 hours

	// }}}
	// {{{ public function getFormattedDuration()

	/**
	 * Returns the duration of the media in a human readable format.
	 *
	 * DateIntervals were dismissed because creating 10-20 SwatDate's per page
	 * seemed slow and excessive. This is a slightly simplified version of
	 * SwatString::toHumanReadableTimePeriod() to allow for custom formatting.
	 * It always returns the two largest time parts.
	 *
	 * @returns string
	 */
	public function getFormattedDuration()
	{
		// don't care about micro-seconds.
		$seconds = floor($this->duration);
		$minutes = 0;

		if ($seconds > 60) {
			$minutes = floor($seconds / 60);
			$seconds -= 60 * $minutes;
		}

		// use sprintf for padding, because I read somewhere on the internet it
		// was faster than str_pad.
		return sprintf('%1$s:%2$02s min',
			$minutes,
			$seconds);
	}

	// }}}

	// Processing methods
	// {{{ public function process()

	public function process(SiteApplication $app, $file_path)
	{
		$this->checkDB();

		try {
			$transaction = new SwatDBTransaction($this->db);

			$this->filename = ($this->getMediaSet()->obfuscate_filename)
				? sha1(uniqid(rand(), true))
				: null;

			$this->createdate = new SwatDate();
			$this->createdate->toUTC();

			$this->save();

			foreach ($this->getMediaSet()->encodings as $encoding) {
				$this->processEncoding($file_path, $encoding);
			}

			// Parse the duration from the first encoding. We do this because
			// we are unable to parse the duration from the $file_path
			// file since it isn't readable or reachable by the AMQP server.
			foreach ($this->getMediaSet()->encodings as $encoding) {
				// Pass an absolute path to the AMQP server.
				$path = realpath($this->getFilePath($encoding->shortname));

				$this->duration = $this->parseDuration($app, $path);
				$this->save();

				break;
			}

			$transaction->commit();
		// TODO: Specialize this.
		} catch (Exception $e) {
			throw $e;
			$transaction->rollback();
		}
	}

	// }}}
	// {{{ public function parseDuration()

	public function parseDuration(SiteApplication $app, $file_path)
	{
		$duration = null;

		if ($app->hasModule('SiteAMQPModule')) {
			$amqp = $app->getModule('SiteAMQPModule');
			$message = array('filename' => $file_path);
			try {
				$result = $amqp->doSyncNs(
					'global',
					'media-duration',
					json_encode($message)
				);

				if ($result['status'] === 'success') {
					$message = json_decode($result['body'], true);
					if ($message !== null) {
						$duration = intval($message['duration']);
					}
				}
			} catch (AMQPConnectionException $e) {
			} catch (AMQPQueueException $e) {
			} catch (SiteAMQPJobFailureException $e) {
				// Ignore connection, queueing or job failure; will just use the
				// old non-amqp code path.
			} catch (Exception $e) {
				// Unknown failure. We can still use the old non-amqp code but
				// Also process the exception so we know what is failing.
				require_once('Site/exceptions/SiteAMQPJobException.php');
				$exception = new SiteAMQPJobException($e);
				$exception->processAndContinue();
			}
		}

		if ($duration === null) {
			$bin = trim(`which ffprobe`);

			// No AMQP or AMQP failed, just run the duration script on this
			// server. Run just the ffprobe first, so we can check it's return
			// code.
			$command = sprintf(
				'%s '.
					'-print_format json '.
					'-select_streams a '.
					'-show_entries format=format_name:format=duration '.
					'-v quiet '.
					'%s ',
				$bin,
				escapeshellarg($file_path)
			);

			$returned_value = 0;
			$command_output = '';
			exec($command, $command_output, $returned_value);

			// If ffprobe has worked, get the format and time from it's output,
			// otherwise throw an exception.
			if ($returned_value === 0) {
				$result = implode('', $command_output);
				$result = json_decode($result, true);

				if ($result !== null &&
					isset($result['format']) &&
					is_array($result['format']) &&
					isset($result['format']['format_name']) &&
					isset($result['format']['duration'])) {

					$format = $result['format']['format_name'];
					$duration = (integer)round($result['format']['duration']);
				}

				if ($duration === null) {
					throw new SiteException(
						'Audio media duration lookup with ffprobe failed. '.
						'Unable to parse format or duration from output.'
					);
				}
			} else {
				throw new SiteException(
					sprintf(
						"Audio media duration lookup with ffprobe failed.\n\n".
						"Ran command:\n%s\n\n".
						"With return code:\n%s",
						$command,
						$returned_value
					)
				);
			}

			// If the file is a MP3 file, ignore the metadata duration and
			// calculate duration based on raw packets.
			if (in_array('mp3', explode(',', mb_strtolower($format)))) {
				$duration = $this->parseMp3Duration(
					$file_path,
					self::FFPROBE_DEFAULT_OFFSET
				);

				if ($duration === null) {
					// Depending on the encoding, some MP3s will return no
					// packets when read_intervals goes past the end of the
					// file. If no packets are returned, run again and read all
					// packets. It's slower, but it works.
					$duration = $this->parseMp3Duration($file_path, 0);
				}

				if ($duration === null) {
					throw new SiteException(
						'Audio media duration lookup with ffprobe failed. '.
						'Unable to parse duration from output.'
					);
				}
			}
		}

		return $duration;
	}

	// }}}
	// {{{ protected function processEncoding()

	protected function processEncoding($file_path, SiteMediaEncoding $encoding)
	{
		$binding = new SiteMediaEncodingBinding();
		$binding->setDatabase($this->db);
		$binding->media = $this->id;
		$binding->media_encoding = $encoding->id;
		$binding->media_type = $encoding->getInternalValue('default_type');
		$binding->filesize = filesize($file_path);
		$binding->save();

		if ($this->getMediaSet()->use_cdn) {
			$this->queueCdnTask('copy', $encoding);
		}

		$directory = $this->getFileDirectory($encoding->shortname);
		if (!file_exists($directory) && !mkdir($directory, 0777, true)) {
			throw new SiteException('Unable to create media directory.');
		}

		$filename = $this->getFilePath($encoding->shortname);
		if (!copy($file_path, $filename)) {
			throw new SiteException('Unable to copy media file.');
		}

		$this->encoding_bindings->add($binding);
	}

	// }}}
	// {{{ protected function parseMp3Duration()

	protected function parseMp3Duration($file_path, $offset)
	{
		$bin = trim(`which ffprobe`);

		$duration = null;

		$command = sprintf(
			'%s '.
				'-print_format json '.
				'-read_intervals %s%% '.
				'-select_streams a '.
				'-show_entries packet=pts_time '.
				'-v quiet '.
				'%s ',
			$bin,
			escapeshellarg($offset),
			escapeshellarg($file_path)
		);

		$returned_value = 0;
		$command_output = '';
		exec($command, $command_output, $returned_value);

		// If ffprobe has worked, get the time from it's output,
		// otherwise throw an exception.
		if ($returned_value === 0) {
			$result = implode('', $command_output);
			$result = json_decode($result, true);

			if ($result !== null &&
				is_array($result['packets']) &&
				count($result['packets']) > 0) {
				$packet = end($result['packets']);
				$duration = (integer)round($packet['pts_time']);
			}
		} else {
			throw new SiteException(
				sprintf(
					"Audio media duration lookup with ffprobe ".
					"failed.\n\n".
					"Ran command:\n%s\n\n".
					"With return code:\n%s",
					$command,
					$returned_value
				)
			);
		}

		return $duration;
	}

	// }}}

	// File and URI methods
	// {{{ public function getContentDispositionFilename()

	public function getContentDispositionFilename($encoding_shortname)
	{
		// Convert to an ASCII string. Approximate non ACSII characters.
		$filename = iconv('UTF-8', 'ASCII//TRANSLIT', $this->original_filename);

		// Format the filename according to the qtext syntax in RFC 822
		$filename = str_replace(array("\\", "\r", "\""),
			array("\\\\", "\\\r", "\\\""), $filename);

		return $filename;
	}

	// }}}
}

?>
