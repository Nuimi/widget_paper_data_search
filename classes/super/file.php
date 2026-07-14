<?php
namespace Classes\Super;

use Classes\DateTimeUtil;
use \Classes\File\Manager as FManager;
use Classes\StaticFunctions;

class File
{
    const CONTR = 'contract';
    const CERT = 'certificate';
    const IMPORT = 'import';
    const ERROR_DICTIONARY = [
        'exist' => 'Soubor %s již existuje',
        'not_image' => 'Soubor %s není obrázek',
        'big' => 'Soubor %s je příliž velký',
        'not_supported' => 'Soubor %s není podporovaný',
        'error' => 'U %s vznikla chyba, pokud se bude opakovat, prosím kontaktujte správce.',
    ];

    const BASE = 'media';

    private array $files;
    private string $type;
    private string $destination;
    private array $error = [];
    private array $success = [];

    public function __construct(array $files, string $type)
    {
        if ($type == self::CONTR)
        {
            $this->setType(self::CONTR);
            $this->setDestination( sprintf('./%s/%s/', self::BASE, 'contracts'));
        }

        if ($type == self::CERT)
        {
            $this->setType(self::CERT);
            $this->setDestination( sprintf('./%s/%s/', self::BASE, 'certificates'));
        }

        if ($type == self::IMPORT)
        {
            $this->setType(self::IMPORT);
            $this->setDestination( sprintf('./%s/%s/', self::BASE, 'import'));
        }

        if ($this->type != '')
        {
            $this->files = $files[$this->type];
        }
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setDestination(string $destination): void
    {
        $this->destination = $destination;
    }

    public function getFileNames()
    {
        return $this->files['name'];
    }

    private function uploadImage(): void
    {
        if (is_array($this->getFileNames()))
        {
            foreach ($this->getFileNames() as $imageIndex => $imageName)
            {
                $fileData = $this->fileNameSetting($imageName);

                if (file_exists($fileData['newFile']))
                {
                    $this->error[$imageName][] = 'exist';
                }

                if (!getimagesize($this->files["tmp_name"][$imageIndex]))
                {
                    $this->error[$imageName][] = 'not_image';
                }

                if ($this->files["size"][$imageIndex] > 999999999)
                {
                    $this->error[$imageName][] = 'big';
                }

                $imageFileType = strtolower($fileData['ext']);
                if($imageFileType != "jpg" &&
                    $imageFileType != "png" &&
                    $imageFileType != "jpeg" &&
                    $imageFileType != "gif" )
                {
                    $this->error[$imageName][] = 'not_supported';
                }

                if (empty($this->error))
                {
                    $this->handleUpload($fileData, $imageIndex, $imageName);
                }
            }
        } else {
            $fileData = $this->fileNameSetting($this->getFileNames());

            if (file_exists($fileData['newFile']))
            {
                $this->error[$this->getFileNames()][] = 'exist';
            }

            if (!getimagesize($this->files["tmp_name"]))
            {
                $this->error[$this->getFileNames()][] = 'not_image';
            }

            if ($this->files["size"] > 999999999)
            {
                $this->error[$this->getFileNames()][] = 'big';
            }

            $imageFileType = strtolower($fileData['ext']);
            if($imageFileType != "jpg" &&
                $imageFileType != "png" &&
                $imageFileType != "jpeg" &&
                $imageFileType != "gif" )
            {
                $this->error[$this->getFileNames()][] = 'not_supported';
            }

            if (empty($this->error))
            {
                $this->handleUpload($fileData, -1, $this->getFileNames());
            }
        }
    }

    private function uploadFile(): void
    {
        if (is_array($this->getFileNames()))
        {
            foreach ($this->getFileNames() as $fileIndex => $fileName)
            {
                $fileData = $this->fileNameSetting($fileName);

                if (file_exists($fileData['newFile']))
                {
                    $this->error[$fileName][] = 'exist';
                }

                if(!in_array(strtolower($fileData['ext']), ["pdf", "xls", "xlsx", "csv"]))
                {
                    $this->error[$fileName][] = 'not_supported';
                }

                if (empty($this->error))
                {
                    $this->handleUpload($fileData, $fileIndex, $fileName);
                }
            }
        } else {
            $fileData = $this->fileNameSetting($this->getFileNames());

            if (file_exists($fileData['newFile']))
            {
                $this->error[$this->getFileNames()][] = 'exist';
            }

            if(!in_array(strtolower($fileData['ext']), ["pdf", "xls", "xlsx", "csv"]))
            {
                $this->error[$this->getFileNames()][] = 'not_supported';
            }

            if (empty($this->error))
            {
                $this->handleUpload($fileData, -1, $this->getFileNames());
            }
        }
    }

    public function upload(): void
    {
        if ($this->type == self::CERT)
        {
            $this->uploadImage();
        }

        if (in_array($this->type, [self::CONTR, self::IMPORT]))
        {
            $this->uploadFile();
        }
    }

    public function manageUploaded(): bool|array
    {
        if (!empty($this->success))
        {
            return $this->success;
        }

        if (!empty($this->error))
        {
            foreach ($this->error as $image => $errors)
            {
                foreach ($errors as $error)
                {
                    StaticFunctions::addErrorMessage(sprintf(self::ERROR_DICTIONARY[$error], $image));
                }
            }
        }
        return false;
    }


    private function fileNameSetting(string $fileName): array
    {
        $target_file = $this->destination . basename($fileName);
        $ext = pathinfo($target_file, PATHINFO_EXTENSION);
        $baseName = $this->getBaseName($ext, $fileName);

        $date = new DateTimeUtil();
        $newName = sprintf('%s-%s.%s', $baseName, $date->getTimestamp(), $ext);
        $new_file = $this->destination.$newName;
        $returnFile = sprintf('%s%s', $this->destination , $newName);
        return [
            'newFile' => $new_file,
            'returnFile' => $returnFile,
            'ext' => $ext,
            'newName' => $newName
        ];
    }

    private function getBaseName(string $ext, string $name): string
    {
        $baseName = explode('.'.$ext ,$name)[0];
        $baseName = str_replace('-', '_', $baseName);
        return str_replace(' ', '', $baseName);
    }

    public static function deleteFile(string $path): void
    {
        if (file_exists('.'.$path))
        {
            unlink('.'.$path);
        }
    }

    private function handleUpload(array $fileData, int $fileIndex, string $fileName): void
    {
        if ($fileIndex == -1)
        {
            if (move_uploaded_file($this->files["tmp_name"], $fileData['newFile']))
            {
                $this->success =  [
                    'path' => substr($fileData['returnFile'], 1),
                    'name' => $fileData['newName'],
                    'type' => $fileData['ext']
                ];
            } else {
                $this->error[$fileName][] = 'error';
            }
        } else {
            if (move_uploaded_file($this->files["tmp_name"][$fileIndex], $fileData['newFile']))
            {
                $this->success[$fileName] =  [
                    'path' => substr($fileData['returnFile'], 1),
                    'name' => $fileData['newName'],
                    'type' => $fileData['ext']
                ];
            } else {
                $this->error[$fileName][] = 'error';
            }
        }
    }
}