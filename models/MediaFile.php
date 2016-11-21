<?php namespace Sewa\Mediafile\Models;

use Config;
use Request;
use October\Rain\Database\Model;
use October\Rain\Database\Attach\File as FileBase;
use October\Rain\Database\Attach\BrokenImage;
use October\Rain\Database\Attach\Resizer;
use October\Rain\Database\Attach\FileException;
use Storage;
use File as FileHelper; // this is Laravel (Illuminate) FileSystem
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\File as FileObj; //TODO: why do we need this?
use Sewa\Mediafile\Classes\MediaFileManager;

/**
 * MediaFile attachment model
 */
class MediaFile extends FileBase
{
    //
    // ######################################################################################################
    // CUSTOM FUNCTIONS
    //
    
    /**
     * 
     */
    public $previewName = 'preview.jpg';
            
    /**
     * get preview Image
     */
    public function getPreviewPath()
    {
        return $this->getPublicPath() . $this->getPartitionDirectory() . $this->previewName;
    }
    
    /**
     * 
     */
    public function getPreviewDiskPath()
    {
        return $this->getStorageDirectory() . $this->getPartitionDirectory() . $this->previewName;
    }
    
    /**
     * 
     */
    public function getPreviewLocalPath()
    {
        if ($this->isLocalStorage()) {
            return $this->getLocalRootPath() . '/' . $this->getPreviewDiskPath();
        }
        else {
            //
            // @todo The CDN portion of this method is not complete.
            // Things to consider:
            // - Generating the temp [cache] file only once
            // - Cleaning up the temporary file somehow
            // - See media manager process as a reference
            //
            return -1;
        }
    }
    
    /**
     * 
     */
    public function deletePreviewThumbs()
    {
        return $this->deleteThumbs();
    }
    
    /**
     * 
     */
    public function getConvertedFileName($type)
    {
        $config = MediaFileManager::instance()->getConversionType($type);
        if(!$config) return null;
        return FileHelper::name($this->disk_name) . $config['suffix'] . $config['extension'];
    }
    
    /**
     * 
     */
    public function getConvertedPath($type)
    {
        return $this->getPublicPath() . $this->getPartitionDirectory() . $this->getConvertedFileName($type);
    }
    
    /**
     * Returns the path to the file, relative to the storage disk.
     * @reutrn string
     */
    public function getConvertedDiskPath($type)
    {
        return $this->getStorageDirectory() . $this->getPartitionDirectory() . $this->getConvertedFileName($type);
    }
    
    /**
     * 
     */
    public function getConvertedLocalPath($type)
    {
        if ($this->isLocalStorage()) {
            return $this->getLocalRootPath() . '/' . $this->getConvertedDiskPath($type);
        }
        else {
            //
            // @todo The CDN portion of this method is not complete.
            // Things to consider:
            // - Generating the temp [cache] file only once
            // - Cleaning up the temporary file somehow
            // - See media manager process as a reference
            //
            return -1;
        }
    }
    
    /**
     * 
     */
    public function hasConvertedFile($type)
    {
        $name = $this->getConvertedFileName($type);
        if(!$name) return false;
        return $this->hasFile($name);
    }
    
    /**
     * Looks for the Converted File in our Queue / Finds Processes, which will convert this file
     */
    public function hasConversionInQueue($type)
    {
        // get file converting processes, which have not started yet
        return Process::ofConvertType()
                ->inQueue()
                ->where('output_file', $this->getConvertedLocalPath($type))
                ->first();
    }
    
    /**
     * Make public path out of file name | For display in browser 
     */
    public function makePath($fileName)
    {
        return $this->getPublicPath() . $this->getPartitionDirectory() . $fileName;
    }
    
    /**
     * Find file by extension, and return it
     */
    public function getExtensionPath($ext)
    {
        // real disk directory
        $dir = $this->getStorageDirectory() . $this->getPartitionDirectory();
        
        // Find all files with this extension.
        // Returns array of 
        // Symfony\Component\Finder\SplFileInfo
        $files = $this->storageCmd('allFiles', $dir);
        
        // filter by extensions
        $files = array_filter($files, function($file)use($ext){
            return $file->getExtension() == $ext;
        });
        $files = array_values($files);
        
        // return path
        if(count($files)>0){
            //print_r($files);
            //exit();
            return $this->makePath( $files[0]->getFileName() );
        }
        return false;
    }
    
    //
    // ######################################################################################################
    // COPIED FROM System\Models\File
    //
    
    /**
     * @var string The database table used by the model.
     */
    protected $table = 'system_files';
    
    /**
     * If working with local storage, determine the absolute local path.
     */
    protected function getLocalRootPath()
    {
        return Config::get('filesystems.disks.local.root', storage_path('app'));
    }

    /**
     * Define the public address for the storage path.
     */
    public function getPublicPath()
    {
        $uploadsPath = Config::get('cms.storage.uploads.path', '/storage/app/uploads');

        if (!starts_with($uploadsPath, ['//', 'http://', 'https://'])) {
            $uploadsPath = Request::getBasePath() . $uploadsPath;
        }

        if ($this->isPublic()) {
            return $uploadsPath . '/public/';
        }
        else {
            return $uploadsPath . '/protected/';
        }
    }
    
    /**
     * Define the internal storage path.
     */
    public function getStorageDirectory()
    {
        $uploadsFolder = Config::get('cms.storage.uploads.folder');

        if ($this->isPublic()) {
            return $uploadsFolder . '/public/';
        }
        else {
            return $uploadsFolder . '/protected/';
        }
    }
    
    //
    // ######################################################################################################
    // OVERRIDDEN FUNCTIONS - October\Rain\Database\Attach\File
    //
    
    /**
     * Generates and returns a thumbnail path.
     */
    public function getThumb($width, $height, $options = [])
    {
        //if (!$this->isImage()) {
        //    return $this->getPath();
        //}
        
        $width = (int) $width;
        $height = (int) $height;
        
        $options = $this->getDefaultThumbOptions($options);
        
        $thumbFile = $this->getThumbFilename($width, $height, $options);
        $thumbPath = $this->getStorageDirectory() . $this->getPartitionDirectory() . $thumbFile;
        $thumbPublic = $this->getPublicPath() . $this->getPartitionDirectory() . $thumbFile;
        
        
        if (!$this->hasFile($thumbFile)) {

            if ($this->isLocalStorage()) {
                $this->makeThumbLocal($thumbFile, $thumbPath, $width, $height, $options);
            }
            else {
                $this->makeThumbStorage($thumbFile, $thumbPath, $width, $height, $options);
            }

        }

        return $thumbPublic;
    }

    /**
     * Generates a thumbnail filename.
     * @return string
     */
    protected function getThumbFilename($width, $height, $options)
    {
        return 'thumb_' . $this->id . '_' . $width . 'x' . $height . '_' . $options['offset'][0] . '_' . $options['offset'][1] . '_' . $options['mode'] . '.' . 'jpg';
    }
    
    /**
     * Generate the thumbnail based on the local file system. This step is necessary
     * to simplify things and ensure the correct file permissions are given
     * to the local files.
     */
    protected function makeThumbLocal($thumbFile, $thumbPath, $width, $height, $options)
    {
        $rootPath = $this->getLocalRootPath();
        $filePath = $rootPath.'/'.$this->getPreviewDiskPath();
        $thumbPath = $rootPath.'/'.$thumbPath;
        
        /*
         * Handle a broken source image
         */
        if (!$this->hasFile($this->previewName)) {
            BrokenImage::copyTo($thumbPath);
        }
        /*
         * Generate thumbnail
         */
        else {
            try {
                Resizer::open($filePath)
                    ->resize($width, $height, $options)
                    ->save($thumbPath)
                ;
            }
            catch (Exception $ex) {
                BrokenImage::copyTo($thumbPath);
            }
        }

        FileHelper::chmod($thumbPath);
    }

    /**
     * Generate the thumbnail based on a remote storage engine.
     */
    protected function makeThumbStorage($thumbFile, $thumbPath, $width, $height, $options)
    {
        $tempFile = $this->getLocalTempPath();
        $tempThumb = $this->getLocalTempPath($thumbFile);

        /*
         * Handle a broken source image
         */
        if (!$this->hasFile($this->previewName)) {
            BrokenImage::copyTo($tempThumb);
        }
        /*
         * Generate thumbnail
         */
        else {
            $this->copyStorageToLocal($this->getPreviewDiskPath(), $tempFile);

            try {
                Resizer::open($tempFile)
                    ->resize($width, $height, $options)
                    ->save($tempThumb)
                ;
            }
            catch (Exception $ex) {
                BrokenImage::copyTo($tempThumb);
            }

            FileHelper::delete($tempFile);
        }

        /*
         * Publish to storage and clean up
         */
        $this->copyLocalToStorage($tempThumb, $thumbPath);
        FileHelper::delete($tempThumb);
    }
}
