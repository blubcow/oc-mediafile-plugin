<?php namespace Sewa\Mediafile\Models;

use Model;

/**
 * Model
 */
class Process extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /*
     * Validation
     */
    public $rules = [
    ];

    /*
     * Disable timestamps by default.
     * Remove this line if timestamps are defined in the database table.
     */
    public $timestamps = false;

    /**
     * @var string The database table used by the model.
     */
    public $table = 'sewa_mediafile_process';
    
    /**
     * Get only active processes
     */
    public function scopeActive($query)
    {
        return $query->whereNotNull('started_at')
                    ->whereNull('finished_at');
    }
    
    /**
     * Get only processes in queue, which have not finished
     */
    public function scopeInQueue($query)
    {
        return $query->where('queue', 1)
                    ->whereNull('finished_at');
    }
    
    /**
     * Get process by plugin commands
     */
    public function scopeOfConvertType($query)
    {
        return $query->where('name', 'mediafile:convert');
    }
    
    public function scopeOfSelectFrameType($query)
    {
        return $query->where('name', 'mediafile:selectframe');
    }
}