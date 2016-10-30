<?php namespace Sewa\Mediafile\Classes;

use File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImageHelper
{
    /**
     * Save uploaded image file in jpeg format
     */
    static function convertUploadedToJpg(UploadedFile $uploadedFile, $outputPath)
    {
        return self::convertToJpg($uploadedFile->getRealPath(), $outputPath, $uploadedFile->getClientMimeType());
    }
    
    /**
     * Convert an image to jpeg format and save to file
     * $mime | Set mime type of input file, to be sure
     */
    static function convertToJpg($inputPath, $outputPath, $mime=false)
    {
        // check if image exists
        if(!File::exists($inputPath))
            return false;
        
        // get mime type
        if(!$mime)
            $mime = File::mimeType($inputPath);
        
        // read image
        if($mime == 'image/jpeg'){
            $img = imagecreatefromjpeg($inputPath);
        }else
        if($mime == 'image/gif'){
            $img = imagecreatefromgif($inputPath);
        }else
        if($mime == 'image/png'){
            $img = imagecreatefrompng($inputPath);
            if($img){
                // make white background
                $bg = imagecreatetruecolor(imagesx($img), imagesy($img));
                imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
                // copy image with transparency onto background
                imagealphablending($bg, TRUE);
                imagecopy($bg, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
                imagedestroy($img);
                $img = $bg;
            }
        }else
        if(in_array($mime, ['image/bmp','image/vnd.wap.wbmp','image/xbm','image/x-ms-bmp'])){
            $img = imagecreatefromwbmp($inputPath);
        }else{
            // mime type is not an image
            return false;
        }
        
        // can't read image
        if(!$img)
            return false;
        
        // save file
        $quality = 70; // 0 = worst / smaller file, 100 = better / bigger file 
        imagejpeg($img, $outputPath, $quality);
        imagedestroy($img);
        
        //
        return true;
    }
}
    