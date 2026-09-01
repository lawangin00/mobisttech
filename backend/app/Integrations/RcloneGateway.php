<?php

namespace App\Integrations;

use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class RcloneGateway
{
    public const REMOTE = 'mobisttech-drive:';

    public function detectExisting(): bool
    {
        try {
            return in_array(self::REMOTE, preg_split('/\R/', trim($this->run(['listremotes'], false))), true) && $this->validate(false);
        } catch (\Throwable) {
            return false;
        }
    }

    public function configure(array $tokens, string $clientId, string $clientSecret): void
    {
        $path = $this->configPath();
        $directory = dirname($path);
        if (str_starts_with(strtolower(str_replace('\\', '/', $directory)), strtolower(str_replace('\\', '/', public_path())))) {
            throw new RuntimeException('rclone configuration cannot be stored under the public web root.');
        }
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Private rclone configuration directory could not be created.');
        }
        $secret = $this->runWithInput(['obscure', '-'], $clientSecret);
        $content = "[mobisttech-drive]\n".
            "type = drive\nclient_id = ".$this->line($clientId)."\nclient_secret = ".$this->line(trim($secret))."\n".
            "scope = drive.file\ntoken = ".$this->line(json_encode($tokens, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))."\n";
        $temp = $path.'.'.Str::random(10).'.tmp';
        file_put_contents($temp, $content, LOCK_EX);
        if (DIRECTORY_SEPARATOR === '/') {
            chmod($temp, 0600);
        }
        rename($temp, $path);
    }

    public function validate(bool $managed = true): bool
    {
        $config = $managed ? true : false;
        $remoteObject = self::REMOTE.'mobiST Tech/Backups/.integration-check-'.Str::uuid().'.txt';
        $local = tempnam(storage_path('app/private'), 'drive-check-');
        try {
            file_put_contents($local, 'mobisttech-drive-validation');
            $this->run(['copyto', $local, $remoteObject], $config);
            $read = $this->run(['cat', $remoteObject], $config);
            $this->run(['deletefile', $remoteObject], $config);

            return trim($read) === 'mobisttech-drive-validation';
        } finally {
            @unlink($local);
            try {
                $this->run(['deletefile', $remoteObject], $config);
            } catch (\Throwable) {
            }
        }
    }

    public function copyTo(string $localPath, string $remotePath, bool $managed = true): void
    {
        $this->run(['copyto', $localPath, self::REMOTE.ltrim($remotePath, '/')], $managed);
    }

    public function deleteRemote(string $remotePath, bool $managed = true): void
    {
        $this->run(['deletefile', self::REMOTE.ltrim($remotePath, '/')], $managed);
    }

    public function disconnectManaged(): void
    {
        $path = $this->configPath();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function configPath(): string
    {
        return config('services.google_drive.rclone_config') ?: storage_path('app/private/integrations/rclone.conf');
    }

    protected function run(array $arguments, bool $managed): string
    {
        $command = [config('services.google_drive.rclone_binary', 'rclone'), ...$arguments];
        if ($managed) {
            array_push($command, '--config', $this->configPath());
        }
        $process = new Process($command, base_path(), ['RCLONE_CONFIG_PASS' => false], null, 30);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException('Google Drive operation failed.');
        }

        return $process->getOutput();
    }

    protected function runWithInput(array $arguments, string $input): string
    {
        $process = new Process([config('services.google_drive.rclone_binary', 'rclone'), ...$arguments], base_path(), [], null, 15);
        $process->setInput($input);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException('rclone secret preparation failed.');
        }

        return $process->getOutput();
    }

    private function line(string $value): string
    {
        if (preg_match('/[\r\n]/', $value)) {
            throw new RuntimeException('Invalid rclone configuration value.');
        }

        return $value;
    }
}
