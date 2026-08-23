<?php

declare(strict_types=1);

namespace App\Actions\Backups;

use App\Actions\Media\StoreLocalImageAction;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Str;
use RuntimeException;
use SplFileInfo;
use Throwable;
use ZipArchive;

final class CreateMediaZipBackupAction
{
    public function __construct(
        private readonly FilesystemManager $filesystem,
        private readonly Filesystem $files,
    ) {}

    /**
     * @return array{path: string, file_count: int, total_bytes: int, sha256: string}
     */
    public function handle(): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The ZIP extension is required to create a media backup.');
        }

        $publicDisk = $this->filesystem->disk('public');
        $privateDisk = $this->filesystem->disk('local');
        $backupDirectory = 'backups/media';
        $privateDisk->makeDirectory($backupDirectory);
        $archivePath = $privateDisk->path($backupDirectory.'/'.Str::uuid()->toString().'.zip');
        $archive = new ZipArchive;
        $archiveIsOpen = false;

        try {
            $openResult = $archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            if ($openResult !== true) {
                throw new RuntimeException('The media ZIP archive could not be opened.');
            }

            $archiveIsOpen = true;
            $manifestFiles = [];
            $totalBytes = 0;

            foreach ($this->mediaFiles($publicDisk->path('media')) as $file) {
                $absolutePath = $file->getRealPath();

                if (! is_string($absolutePath)) {
                    continue;
                }

                $relativePath = $this->relativePath($publicDisk->path('media'), $absolutePath);
                $archiveName = 'media/'.$relativePath;
                $size = $this->files->size($absolutePath);
                $sha256 = hash_file('sha256', $absolutePath);

                if (! is_string($sha256) || ! $archive->addFile($absolutePath, $archiveName)) {
                    throw new RuntimeException('A media file could not be added to the ZIP archive.');
                }

                $manifestFiles[] = [
                    'path' => $archiveName,
                    'sha256' => $sha256,
                    'size' => $size,
                ];
                $totalBytes += $size;
            }

            $manifest = json_encode([
                'schema_version' => 1,
                'generated_at' => now()->toIso8601String(),
                'storage_root' => 'media',
                'file_count' => count($manifestFiles),
                'total_bytes' => $totalBytes,
                'files' => $manifestFiles,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            if (! $archive->addFromString('manifest.json', $manifest)) {
                throw new RuntimeException('The media ZIP archive could not be finalized.');
            }

            if (! $archive->close()) {
                $archiveIsOpen = false;

                throw new RuntimeException('The media ZIP archive could not be finalized.');
            }

            $archiveIsOpen = false;
            $this->files->chmod($archivePath, 0600);
            $sha256 = hash_file('sha256', $archivePath);

            if (
                ! $this->files->isFile($archivePath)
                || $this->files->size($archivePath) < 100
                || ! is_string($sha256)
            ) {
                throw new RuntimeException('The generated media ZIP archive is invalid.');
            }

            return [
                'path' => $archivePath,
                'file_count' => count($manifestFiles),
                'total_bytes' => $totalBytes,
                'sha256' => $sha256,
            ];
        } catch (Throwable $exception) {
            if ($archiveIsOpen) {
                $archive->close();
            }

            $this->files->delete($archivePath);

            throw new RuntimeException('Unable to create the media ZIP backup.', previous: $exception);
        }
    }

    /**
     * @return list<SplFileInfo>
     */
    private function mediaFiles(string $mediaDirectory): array
    {
        $mediaRoot = realpath($mediaDirectory);

        if (! is_string($mediaRoot) || ! $this->files->isDirectory($mediaRoot)) {
            return [];
        }

        $allowedExtensions = StoreLocalImageAction::allowedExtensions();
        $files = collect($this->files->allFiles($mediaRoot))
            ->filter(function (SplFileInfo $file) use ($allowedExtensions, $mediaRoot): bool {
                if ($file->isLink() || ! $file->isReadable()) {
                    return false;
                }

                $realPath = $file->getRealPath();

                return is_string($realPath)
                    && $this->isWithinDirectory($realPath, $mediaRoot)
                    && in_array(strtolower($file->getExtension()), $allowedExtensions, true);
            })
            ->sortBy(fn (SplFileInfo $file): string => str_replace('\\', '/', $file->getPathname()))
            ->values()
            ->all();

        return $files;
    }

    private function relativePath(string $mediaDirectory, string $absolutePath): string
    {
        $mediaRoot = realpath($mediaDirectory);

        if (! is_string($mediaRoot) || ! $this->isWithinDirectory($absolutePath, $mediaRoot)) {
            throw new RuntimeException('The media path is outside the configured storage root.');
        }

        return ltrim(str_replace('\\', '/', substr($absolutePath, strlen($mediaRoot))), '/');
    }

    private function isWithinDirectory(string $path, string $directory): bool
    {
        $normalizedDirectory = rtrim(str_replace('\\', '/', $directory), '/').'/';
        $normalizedPath = str_replace('\\', '/', $path);

        return str_starts_with($normalizedPath, $normalizedDirectory);
    }
}
