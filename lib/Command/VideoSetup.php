<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022, Varun Patil <radialapps@gmail.com>
 * @author Varun Patil <radialapps@gmail.com>
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Memories\Command;

use OCP\IConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class VideoSetup extends Command
{
    protected IConfig $config;
    protected OutputInterface $output;

    public function __construct(
        IConfig $config
    ) {
        parent::__construct();
        $this->config = $config;
    }

    protected function configure(): void
    {
        $this
            ->setName('memories:video-setup')
            ->setDescription('Setup video streaming')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Check nohup binary
        $nohup = shell_exec('nohup --version');
        if (!$nohup || false === strpos($nohup, 'nohup')) {
            $output->writeln('<error>nohup binary not found. Please install nohup.</error>');

            return $this->suggestDisable($output);
        }

        // Get ffmpeg version
        $ffmpeg = shell_exec('ffmpeg -version');
        if (false === strpos($ffmpeg, 'ffmpeg version')) {
            $ffmpeg = null;
            $output->writeln('<error>ffmpeg is not installed</error>');
        } else {
            $output->writeln('ffmpeg is installed');
        }

        // Get ffprobe version
        $ffprobe = shell_exec('ffprobe -version');
        if (false === strpos($ffprobe, 'ffprobe version')) {
            $ffprobe = null;
            $output->writeln('<error>ffprobe is not installed</error>');
        } else {
            $output->writeln('ffprobe is installed');
        }

        if (null === $ffmpeg || null === $ffprobe) {
            $output->writeln('ffmpeg and ffprobe are required for video transcoding');

            return $this->suggestDisable($output);
        }

        // Check go-transcode binary
        $output->writeln('Checking for go-transcode binary');

        // Detect architecture
        $arch = \OCA\Memories\Util::getArch();
        $libc = \OCA\Memories\Util::getLibc();

        if (!$arch || !$libc) {
            $output->writeln('<error>Compatible go-transcode binary not found</error>');
            $this->suggestGoTranscode($output);

            return $this->suggestDisable($output);
        }

        $goTranscodePath = realpath(__DIR__."/../../exiftool-bin/go-transcode-{$arch}-{$libc}");
        $output->writeln("Trying go-transcode from {$goTranscodePath}");

        $goTranscode = shell_exec($goTranscodePath.' --help');
        if (!$goTranscode || false === strpos($goTranscode, 'Available Commands')) {
            $output->writeln('<error>go-transcode could not be run</error>');
            $this->suggestGoTranscode($output);

            return $this->suggestDisable($output);
        }

        // Go transcode is working. Yay!
        $output->writeln('go-transcode is installed!');
        $output->writeln('');
        $output->writeln('You can use transcoding and HLS streaming');
        $output->writeln('This is recommended for better performance, but has implications if');
        $output->writeln('you are using external storage or run Nextcloud on a slow system.');
        $output->writeln('');
        $output->writeln('Read the following documentation carefully before continuing:');
        $output->writeln('https://github.com/pulsejet/memories/wiki/Configuration');
        $output->writeln('');
        $output->writeln('Do you want to enable transcoding and HLS? [Y/n]');

        $handle = fopen('php://stdin', 'r');
        $line = fgets($handle);
        if ('n' === trim($line)) {
            $this->config->setSystemValue('memories.no_transcode', true);
            $output->writeln('<error>Transcoding and HLS are now disabled</error>');

            return 0;
        }

        $tConfig = realpath(__DIR__.'/../../transcoder.yaml');

        $this->config->setSystemValue('memories.transcoder', $goTranscodePath);
        $this->config->setSystemValue('memories.transcoder_config', $tConfig);
        $this->config->setSystemValue('memories.no_transcode', false);
        $output->writeln('Transcoding and HLS are now enabled!');

        return 0;
    }

    protected function suggestGoTranscode(OutputInterface $output): void
    {
        $output->writeln('You may build go-transcode from source');
        $output->writeln('It can be downloaded from https://github.com/pulsejet/go-transcode');
        $output->writeln('Once built, point the path to the binary in the config for `memories.transcoder`');
    }

    protected function suggestDisable(OutputInterface $output)
    {
        $output->writeln('Without transcoding, video playback may be slow and limited');
        $output->writeln('Do you want to disable transcoding and HLS streaming? [y/N]');
        $handle = fopen('php://stdin', 'r');
        $line = fgets($handle);
        if ('y' !== trim($line)) {
            $output->writeln('Aborting');

            return 1;
        }

        $this->config->setSystemValue('memories.no_transcode', true);
        $output->writeln('<error>Transcoding and HLS are now disabled</error>');

        return 0;
    }
}