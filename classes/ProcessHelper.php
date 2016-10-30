<?php namespace Sewa\Mediafile\Classes;

use Sewa\Mediafile\Models\Process;

class ProcessHelper
{
    /**
     * Execute Process
     */
    static function execute(Process $process)
    {
        $process->started_at = date("Y-m-d H:i:s");
        $process->save();
        
        exec($process->command, $output, $err);
        
        // TODO: create tmp files and send the stdout & stderr there
        //$command .=' 2> E:\\first_tmp.txt > E:\\second_tmp.txt';
        
        $process->stdout = json_encode($output);
        $process->stderr = json_encode($err);
        $process->finished_at = date("Y-m-d H:i:s");
        $process->save();
        
        return $process;
    }
    
    /**
     * Execute next in queue
     */
    static function executeNextQueue()
    {
        if(ProcessHelper::isQueueRunning() === null){
            // find next process in queue
            $process = ProcessHelper::getNextQueue();
            if($process === null){
                return 'NO PROCESSES FOUND';
            }
            
            // execute our process
            $executedProcess = ProcessHelper::execute($process);
            
            // try executing next process in queue
            if($executedProcess){
                ProcessHelper::executeNextQueue();
            }
            
            return $executedProcess;
        }else{
            return true;
        }
    }
    
    /**
     * check if some process in queue is running
     */
    static function isQueueRunning()
    {
        // "inQueue" scope selects unfinished queue processes
        return Process::inQueue()
                ->whereNotNull('started_at')
                ->first();
    }
    
    /**
     * find next process in queue
     */
    static function getNextQueue()
    {
        return Process::inQueue()
                ->orderBy('created_at', 'ASC')
                ->first();
    }
}
    