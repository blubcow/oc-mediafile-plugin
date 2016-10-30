<?php namespace Sewa\Mediafile\Controllers;

use Backend\Classes\Controller;
use BackendMenu;

class Process extends Controller
{
    public $implement = ['Backend\Behaviors\ListController'];
    
    public $listConfig = 'list.yaml';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Sewa.Mediafile', 'mediafile', 'process');
    }
    
    public function index()
    {
        $this->asExtension('ListController')->index();
    }
}