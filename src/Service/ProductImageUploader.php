<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class ProductImageUploader
{
    private string $uploadDirectory;

    public function __construct(
        private readonly SluggerInterface $slugger,
        string $projectDir,
    ) {
        $this->uploadDirectory = $projectDir . '/public/uploads';
    }

    public function upload(UploadedFile $imageFile): string
    {
        $originalFilename = pathinfo(
            $imageFile->getClientOriginalName(),
            PATHINFO_FILENAME
        );

        $safeFilename = $this->slugger->slug(
            $originalFilename
        );

        $newFilename =
            $safeFilename
            . '-'
            . uniqid()
            . '.'
            . $imageFile->guessExtension();

        $imageFile->move(
            $this->uploadDirectory,
            $newFilename
        );

        return $newFilename;
    }

    public function delete(?string $filename): void
    {
        if (!$filename) {
            return;
        }

        if (
            str_starts_with($filename, 'http://')
            || str_starts_with($filename, 'https://')
        ) {
            return;
        }

        $filepath = $this->uploadDirectory . '/' . $filename;

        if (is_file($filepath)) {
            unlink($filepath);
        }
    }

    public function replace(
        ?string $oldFilename,
        UploadedFile $newImage
    ): string {
        $newFilename = $this->upload($newImage);

        $this->delete($oldFilename);

        return $newFilename;
    }
}
