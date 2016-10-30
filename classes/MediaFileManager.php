<?php namespace Sewa\Mediafile\Classes;

use ApplicationException;
use Config;
use Sewa\Mediafile\Models\Process;
use Sewa\Mediafile\Models\MediaFile;
use File;

class MediafileManager
{
    use \October\Rain\Support\Traits\Singleton;
    
    protected $ffmpegPath;
    
    // we don't use "system", out of IDEs confusion with the system() function
    protected $os;
    
    protected $conversionTypes;
    
    /**
     * 
     */
    protected function init()
    {
        // setup our private vars
        $this->ffmpegPath = Config::get('sewa.mediafile::ffmpegPath');
        $this->os = Config::get('sewa.mediafile::system');
        $this->conversionTypes = Config::get('sewa.mediafile::conversionTypes');
                
        // check if ffmpeg path has been set
        if(!$this->ffmpegPath) throw new ApplicationException('ffmpegPath configuration missing!');
        
        // auto assign operating system
        if(!$this->os || ($this->os == 'auto')){
            $uname = strtolower(php_uname());
            if(strpos($uname, "darwin") !== false){
                $this->os = 'mac';
            }elseif(strpos($uname, "win") !== false){
                $this->os = 'win';
            }elseif(strpos($uname, "linux") !== false){
                $this->os = 'linux';
            }else{
                throw new ApplicationException("Cant configure operating system!");
            }
        }
    }
    
    //################################################################################
    
    /**
     * Add conversion type
     */
    public function addConversionType($key, $config)
    {
        if(	isset($config['suffix']) &&
            isset($config['extension']) &&
            isset($config['ffmpegOptions'])){
            $this->conversionTypes[$key] = $config;
        }else{
            return false;
        }
    }
    
    /**
     * Add an array of conversion types (same format as in config file)
     */
    public function addConversionTypes($types)
    {
        foreach($types as $key => $value){
            $this->addConversionType($key, $value);
        }
        return $this->conversionTypes;
    }
    
    /**
     * 
     */
    public function resetConversionTypes()
    {
        $this->conversionTypes = [];
        return true;
    }
    
    /**
     * Return requested type configuration by key
     */
    public function getConversionType($type)
    {
        return isset($this->conversionTypes[$type]) ? $this->conversionTypes[$type] : null ;
    }
    
    //################################################################################
    
    /**
     * Returns ffmpeg path to execute
     */
    public function getFfmpegPath()
    {
        return $this->ffmpegPath;
    }
    
    /**
     * Return operating system
     * 'mac', 'win' or 'linux'
     */
    public function getSystem()
    {
        return $this->os;
    }
    
    //
    //################################################################################
    //
    
    /**
     * Push a command into background
     * TODO: Make OSX background command
     */
    public function executeInBackground($command)
    {
        if($this->getSystem() == 'win'){ 
            pclose(popen('start /B '.$command.' 2>nul >nul', "r")); // windows background command
        }else{
            exec($command.' 2>nul >nul'); // linux background command
        }
        sleep(2);
        return true;
    }
    
    
    /**
     * Convert file so a specific type
     */
    public function convert(MediaFile $file, $type, $background=false)
    {
        if($background){
            // run artisan command in background
            $command = 'php artisan mediafile:convert -i '.$file->getLocalPath().' -o '.$file->getConvertedLocalPath($type).' -t '.$type;
            $command .= $background ? ' --queue': '';
            return $this->executeInBackground($command);
        }else{
            // create process and execute right now
            $process = $this->getConvertProcess($file->getLocalPath(), $file->getConvertedLocalPath($type), $type);
            return ProcessHelper::execute($process);
        }
    }
    
    /**
     * Create new Process Model
     * $i = inputPath
     * $o = outputPath
     * $t = type (key of conversion configs)
     */
    public function getConvertProcess($i, $o, $t)
    {
        // create command
        $ffmpegCommand = $this->getConversionType($t)['ffmpegOptions'];
        $command = $this->getFfmpegPath().' -i '.$i.' '.$ffmpegCommand.' -y '.$o;
        
        // add process / make queue
        $process = Process::forceCreate([
            'name' => 'convertFile',
            'description' => 'convert file from '.File::extension($i).' to '.File::extension($o).' ('.File::name($o).'.'.File::extension($o).')',		
            'command' => $command,
            'input_file' => $i,
            'output_file' => $o,
            'created_at' => date("Y-m-d H:i:s")
        ]);
        return $process;
    }
    
    
    /**
     * Select video frame by timecode, and save it as a cover image
     */
    public function selectFrame(MediaFile $file, $timecode, $background=false)
    {
        if($background){
            // run artisan command in background
            $command = 'php artisan mediafile:selectframe -i '.$file->getLocalPath().' -o '.$file->getPreviewLocalPath().' -t '.$timecode;
            $command .= $background ? ' --queue': '';
            return $this->executeInBackground($command);
        }else{
            // create process and execute right now
            $process = $this->getSelectFrameProcess($file->getLocalPath(), $file->getPreviewLocalPath(), $timecode);
            return ProcessHelper::execute($process);
        }
    }
    
    /**
     * Create new Process Model
     * $i = inputPath
     * $o = outputPath
     * $t = timecode
     */
    public function getSelectFrameProcess($i, $o, $t)
    {
        // create command string
        $ffmpegCommand = '-ss '.$t.' -qscale:v 2 -vframes 1 -f image2';
        $command = $this->getFfmpegPath().' -i '.$i.' '.$ffmpegCommand.' -y '.$o;
                
        // add process / make queue
        $process = Process::forceCreate([
            'name' => 'selectFrame',
            'description' => 'export frame from '.File::name($i).'.'.File::extension($i).' at '.$t,		
            'command' => $command,
            'input_file' => $i,
            'output_file' => $o,
            'created_at' => date("Y-m-d H:i:s")
        ]);
        return $process;
    }	
}