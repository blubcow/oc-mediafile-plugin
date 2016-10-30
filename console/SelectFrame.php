<?php namespace Sewa\Mediafile\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

use Sewa\Mediafile\Classes\ProcessHelper;
use Sewa\Mediafile\Classes\MediaFileManager;

class SelectFrame extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'mediafile:selectframe';

    /**
     * @var string The console command description.
     */
    protected $description = 'Get image from videos with ffmpeg';

    /**
     * Execute the console command.
     * @return void
     */
    public function fire()
    {
        // get options
        $i = $this->option('input');
        $o = $this->option('output');
        $t = $this->option('timecode'); // HH:MM:SS.MS
        //$s = $this->option('seconds'); // TODO: add this option
        $queue = $this->option('queue');
        
        // get process
        $process = MediaFileManager::instance()->getSelectFrameProcess($i, $o, $t);
        $process->name = $this->name;
        $process->queue = $queue ? 1 : 0;
        $process->save();
        
        // execute command
        if($queue){
            $execute = ProcessHelper::executeNextQueue(); // check for next queue to execute
        }else{
            $execute = ProcessHelper::execute($process); // execute command immediatly
        }
        
        // return values
        if($execute === true){
            $this->info('PROCESS SENT TO QUEUE');
            return 0; //return success
        }else
        if($process){
            $this->info('PROCESS EXECUTED');
            foreach($process->toArray() as $key => $val){
                $this->info($key.': '.$val);
            }
            return 0; //return success
        }else{
            $this->error('COULD NOT CREATE OR RUN PROCESS');
            return 1; //return error
        }
    }

    /**
     * Get the console command arguments.
     * @return array
     */
    protected function getArguments()
    {
        return [];
    }

    /**
     * Get the console command options.
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['input', 'i', InputOption::VALUE_REQUIRED, 'Input video file | required'],
            ['output', 'o', InputOption::VALUE_REQUIRED, 'Output image file to save to | required'],
            
            ['timecode', 't', InputOption::VALUE_OPTIONAL, 'Position in timecode to export | required'], // use sexagesimal format (HH:MM:SS.MS)
            // TODO: use seconds
            //['seconds', 's', InputOption::VALUE_OPTIONAL, 'Position in seconds to export | required'], // use float values
            
            ['queue', null, InputOption::VALUE_NONE, 'Adds to queue | switch'],
        ];
    }

}