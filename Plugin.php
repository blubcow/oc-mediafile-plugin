<?php namespace Sewa\Mediafile;

use System\Classes\PluginBase;
use System\Classes\CombineAssets;
use App;
use Event;

class Plugin extends PluginBase
{
    public function boot()
    {
        // Check if we are currently in backend module.
        if (!App::runningInBackend()) {
            return;
        }
    }

    public function registerComponents()
    {
    }

    public function registerSettings()
    {
    }
    
    public function register()
    {
        $this->registerConsoleCommand('mediafile:convert', 'Sewa\Mediafile\Console\Convert');
        $this->registerConsoleCommand('mediafile:selectframe', 'Sewa\Mediafile\Console\SelectFrame');
        
        CombineAssets::registerCallback(function($combiner) {
            $combiner->registerBundle('~/plugins/sewa/mediafile/formwidgets/mediafileupload/assets/less/mediafileupload.less');
        });
    }

    public function registerFormWidgets()
    {
        return [
            'Sewa\Mediafile\FormWidgets\MediaFileUpload' => [
                'label' => 'MediaFileUpload',
                'code'  => 'mediafileupload'
            ]
        ];
    }
}
