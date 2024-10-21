<?php

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\SluggerInterface;

class FileService
{
    private SluggerInterface $slugger;
    private string $imagesDirectory;
    private string $portfolioDirectory;

    public function __construct(SluggerInterface $slugger, string $imagesDirectory, string $portfolioDirectory)
    {
        $this->slugger = $slugger;
        $this->imagesDirectory = $imagesDirectory;
        $this->portfolioDirectory = $portfolioDirectory;
    }

    /**
     * @param mixed $file
     * @return string
     */
    public function setFileAsImage(mixed $file): string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        // this is needed to safely include the file name as part of the URL
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilePath = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        // Move the file to the directory where images are stored
        try {

            $file->move($this->imagesDirectory, $newFilePath);
            $filesystem = new Filesystem();
            $filesystem->copy(
                $this->imagesDirectory . '/' . $newFilePath,
                $this->portfolioDirectory . '/' . $newFilePath, true);

        } catch (FileException $e) {
            // ... handle exception if something happens during file upload
        }

        return $newFilePath;
    }

}