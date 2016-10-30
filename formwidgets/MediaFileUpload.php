<?php namespace Sewa\Mediafile\FormWidgets;

use Str;
use Input;
use Request;
use Response;
use Validator;
use Backend\Classes\FormField;
use Backend\Classes\FormWidgetBase;
use Backend\Controllers\Files as FilesController;
use Sewa\Mediafile\Classes\MediaFileManager;
use October\Rain\Filesystem\Definitions as FileDefinitions;
use ApplicationException;
use ValidationException;
use Exception;
use Sewa\Mediafile\Classes\ImageHelper;
use URL;

/**
 * File upload field
 * Renders a form file uploader field.
 *
 * Supported options:
 * - mode: image-single, image-multi, file-single, file-multi
 * - upload-label: Add file
 * - empty-label: No file uploaded
 */
class MediaFileUpload extends FormWidgetBase
{
    use \Backend\Traits\FormModelWidget;

    //
    // Configurable properties
    //

    /**
     * @var string Prompt text to display for the upload button.
     */
    public $prompt = null;

    /**
     * @var int Preview image width
     */
    public $imageWidth = 200;

    /**
     * @var int Preview image height
     */
    public $imageHeight = 200;

    /**
     * @var mixed Collection of acceptable file types.
     */
    public $fileTypes = false;

    /**
     * @var mixed Collection of acceptable mime types.
     */
    public $mimeTypes = false;
    
    /**
     * @var array Options used for generating thumbnails.
     */
    public $thumbOptions = [
        'mode'      => 'crop',
        'extension' => 'auto'
    ];

    /**
     * @var boolean Allow the user to set a caption.
     */
    public $useCaption = true;
    
    /**
     * @var array Available conversion types
     */
    public $conversionTypes = ['h264','webm','mp3'];
    

    //
    // Object properties
    //

    /**
     * {@inheritDoc}
     */
    protected $defaultAlias = 'mediafileupload';

    /**
     * {@inheritDoc}
     */
    public function init()
    {
        $this->fillFromConfig([
            'prompt',
            'imageWidth',
            'imageHeight',
            'fileTypes',
            'mimeTypes',
            'thumbOptions',
            'useCaption',
            
            'conversionTypes'
        ]);

        $this->checkUploadPostback();
    }
    

    /**
     * {@inheritDoc}
     */
    public function render()
    {
        $this->prepareVars();
        return $this->makePartial('mediafileupload');
    }
    
    /**
     * {@inheritDoc}
     */
    protected function loadAssets()
    {
        $this->addCss('css/mediafileupload.css', 'core');
        $this->addJs('js/mediafileupload.js', 'core');
        
        // Mediaelement-JS
        $this->addJs('/plugins/sewa/mediafile/assets/mediaelement-js/mediaelement-and-player.js');
        $this->addCss('/plugins/sewa/mediafile/assets/mediaelement-js/mediaelementplayer.css');
    }

    /**
     * {@inheritDoc}
     */
    public function getSaveValue($value)
    {
        return FormField::NO_SAVE_DATA;
    }

    /**
     * Prepares the view data
     */
    protected function prepareVars()
    {
        $this->vars['fileList'] = $fileList = $this->getFileList();
        $this->vars['singleFile'] = $fileList->first();
        $this->vars['displayMode'] = $this->getDisplayMode();
        $this->vars['emptyIcon'] = $this->getConfig('emptyIcon', 'icon-upload');
        $this->vars['imageHeight'] = $this->imageHeight;
        $this->vars['imageWidth'] = $this->imageWidth;
        $this->vars['acceptedFileTypes'] = $this->getAcceptedFileTypes(true);
        $this->vars['cssDimensions'] = $this->getCssDimensions();
        $this->vars['cssBlockDimensions'] = $this->getCssDimensions('block');
        $this->vars['useCaption'] = $this->useCaption;
        $this->vars['prompt'] = $this->getPromptText();
        
        $this->vars['conversionTypes'] = $this->conversionTypes;
        $this->vars['previewUpdate'] = false;
        $this->vars['mode'] = starts_with($this->getDisplayMode(), 'audio') ? 'audio' : 'video';
    }
    
    //
    // ###############################################################################################
    // CUSTOM FUNCTIONS
    //
    
    /**
     * 
     */
    protected function getRelationMode()
    {
        $mode = $this->getConfig('mode');
        
        if(str_contains($mode, '-')) {
            return ends_with($mode, 'multi') ? 'multi' : 'single';
        }

        $relationType = $this->getRelationType();
        return ($relationType == 'attachMany' || $relationType == 'morphMany') ? 'multi' : 'single';
    }
    
    /**
     * Start file conversion (in background)
     * NEEDS:
     * - post('convert_id')
     * - post('type')
     */
    public function onConvertFile()
    {
        try {
            // find MediaFile Model to convert
            $fileModel = $this->getRelationModel();
            if (($fileId = post('convert_id')) && ($file = $fileModel::find($fileId))) {
                if($type = post('type')){
                    // start conversion - in background
                    $convert = MediaFileManager::instance()->convert($file, $type, true);
                }
            }
                    
            if(!$convert || !isset($convert))
                throw new ApplicationException('Conversion failed (Artisan command)');
            
            // return partial
            $this->prepareVars();
            return ['#'.$this->getId() => $this->makePartial( 'item_'.$this->getRelationMode() )];
        }
        catch (Exception $ex) {
            return json_encode(['error' => $ex->getMessage()]);
        }
    }
    
    /**
     * Upload preview (cover) image
     * NEEDS:
     * - post('file_id')
     * - Input::file('preview_data')
     */
    public function onPreviewUpload()
    {
        try {
            if (!Input::hasFile('preview_data')) {
                throw new ApplicationException('File missing from request');
            }

            $uploadedFile = Input::file('preview_data');			
            $validationRules[] = 'extensions:'.implode(',', FileDefinitions::get('imageExtensions'));			
            $validation = Validator::make(
                ['preview_data' => $uploadedFile],
                ['preview_data' => $validationRules]
            );
            if($validation->fails()){
                throw new ValidationException($validation);
            }
            if(!$uploadedFile->isValid()){
                throw new ApplicationException('File is not valid');
            }
            
            // find MediaFile Model to save image to
            $fileModel = $this->getRelationModel();
            if(($fileId = post('file_id')) && ($mediaFile = $fileModel::find($fileId)))
            {
                // convert and save to file
                $convertSuccess = ImageHelper::convertUploadedToJpg($uploadedFile, $mediaFile->getPreviewLocalPath());
                if(!$convertSuccess)
                    throw new ApplicationException('Could not upload file OR convert file to JPG');
                
                // delete previous thumbs
                $mediaFile->deletePreviewThumbs();
            }else{
                throw new ApplicationException('No file_id set OR cant find MediaFile Model');
            }
        }
        catch (Exception $ex) {
            return Response::json($ex->getMessage(), 400);
        }
        
        // return partial
        $this->prepareVars();
        return ['#'.$this->getId() => $this->makePartial( 'item_'.$this->getRelationMode(), [
            'previewUpdate'=>true
        ])];
    }

    /**
     * Load the frame selector popup
     * NEEDS:
     * - post('file_id')
     */
    public function onLoadSelectFrame()
    {
        $fileModel = $this->getRelationModel();
        if (($fileId = post('file_id')) && ($file = $fileModel::find($fileId))) {
            $file = $this->decorateFileAttributes($file);
                        
            $this->vars['file'] = $file;
            $this->vars['displayMode'] = $this->getDisplayMode();
            $this->vars['cssDimensions'] = $this->getCssDimensions();
            $this->vars['relationManageId'] = post('manage_id');
            $this->vars['relationField'] = post('_relation_field');

            return $this->makePartial('select_frame');
        }

        throw new ApplicationException('Unable to find file, it may no longer exist');
    }

    /**
     * Frame selected, now export an image and update the file
     * NEEDS:
     * - post('file_id')
     * - post('timecode')
     */
    public function onSaveSelectFrame()
    {
        try {
            $fileModel = $this->getRelationModel();
            if (($fileId = post('file_id')) && ($file = $fileModel::find($fileId))) {
                
                // call conversion in controller
                $success = MediaFileManager::instance()->selectFrame($file, post('timecode'));
                                        
                if(!$success)
                    throw new ApplicationException('Convert frame for preview failed (Artisan command)');
                
                // delete previous thumbs
                $file->deletePreviewThumbs();
                
                // return partial
                $this->prepareVars();
                return ['#'.$this->getId() => $this->makePartial( 'item_'.$this->getRelationMode(), [
                    'previewUpdate'=>true
                ])];
            }

            throw new ApplicationException('Unable to find file, it may no longer exist');
        }
        catch (Exception $ex) {
            return json_encode(['error' => $ex->getMessage()]);
        }
    }
    
    //
    // ###############################################################################################
    // COPIED FUNCTIONS - from FileUpload Widget
    //
    
    protected function getFileList()
    {
        $list = $this
            ->getRelationObject()
            ->withDeferred($this->sessionKey)
            ->orderBy('sort_order')
            ->get()
        ;

        /*
         * Decorate each file with thumb and custom download path
         */
        $list->each(function($file) {
            $this->decorateFileAttributes($file);
        });

        return $list;
    }

    /**
     * Returns the display mode for the file upload. Eg: file-multi, image-single, etc.
     * @return string
     */
    protected function getDisplayMode()
    {
        $mode = $this->getConfig('mode', 'video');

        if (str_contains($mode, '-')) {
            return $mode;
        }

        $relationType = $this->getRelationType();
        $mode .= ($relationType == 'attachMany' || $relationType == 'morphMany') ? '-multi' : '-single';

        return $mode;
    }

    /**
     * Returns the escaped and translated prompt text to display according to the type.
     * @return string
     */
    protected function getPromptText()
    {
        if ($this->prompt === null) {
            $isMulti = ends_with($this->getDisplayMode(), 'multi');
            $this->prompt = $isMulti
                ? 'backend::lang.fileupload.upload_file'
                : 'backend::lang.fileupload.default_prompt';
        }

        return str_replace('%s', '<i class="icon-upload"></i>', e(trans($this->prompt)));
    }

    /**
     * Returns the CSS dimensions for the uploaded image,
     * uses auto where no dimension is provided.
     * @param string $mode
     * @return string
     */
    protected function getCssDimensions($mode = null)
    {
        if (!$this->imageWidth && !$this->imageHeight) {
            return '';
        }

        $cssDimensions = '';

        if ($mode == 'block') {
            $cssDimensions .= ($this->imageWidth)
                ? 'width: '.$this->imageWidth.'px;'
                : 'width: '.$this->imageHeight.'px;';

            $cssDimensions .= ($this->imageHeight)
                ? 'height: '.$this->imageHeight.'px;'
                : 'height: auto;';
        }
        else {
            $cssDimensions .= ($this->imageWidth)
                ? 'width: '.$this->imageWidth.'px;'
                : 'width: auto;';

            $cssDimensions .= ($this->imageHeight)
                ? 'height: '.$this->imageHeight.'px;'
                : 'height: auto;';
        }

        return $cssDimensions;
    }

    /**
     * Returns the specified accepted file types, or the default
     * based on the mode. Video mode will return:
     * - mp4,avi,mov,...
     * @return string
     */
    public function getAcceptedFileTypes($includeDot = false)
    {
        $types = $this->fileTypes;

        if ($types === false) {
            // take this if we are combining video and audio
            $isVideo = starts_with($this->getDisplayMode(), 'video');
            $types = implode(',', FileDefinitions::get($isVideo ? 'videoExtensions' : 'audioExtensions'));
            //$types = implode(',', FileDefinitions::get('videoExtensions'));
        }

        if (!$types || $types == '*') {
            return null;
        }

        if (!is_array($types)) {
            $types = explode(',', $types);
        }

        $types = array_map(function($value) use ($includeDot) {
            $value = trim($value);

            if (substr($value, 0, 1) == '.') {
                $value = substr($value, 1);
            }

            if ($includeDot) {
                $value = '.'.$value;
            }

            return $value;
        }, $types);

        return implode(',', $types);
    }
    
    
    /**
     * Removes a file attachment.
     */
    public function onRemoveAttachment()
    {
        $fileModel = $this->getRelationModel();
        if (($fileId = post('file_id')) && ($file = $fileModel::find($fileId))) {
            $this->getRelationObject()->remove($file, $this->sessionKey);
        }
    }

    /**
     * Sorts file attachments.
     */
    public function onSortAttachments()
    {
        if ($sortData = post('sortOrder')) {
            $ids = array_keys($sortData);
            $orders = array_values($sortData);

            $fileModel = $this->getRelationModel();
            $fileModel->setSortableOrder($ids, $orders);
        }
    }
    
    /**
     * Loads the configuration form for an attachment, allowing title and description to be set.
     */
    public function onLoadAttachmentConfig()
    {
        $fileModel = $this->getRelationModel();
        if (($fileId = post('file_id')) && ($file = $fileModel::find($fileId))) {
            $file = $this->decorateFileAttributes($file);

            $this->vars['file'] = $file;
            $this->vars['displayMode'] = $this->getDisplayMode();
            $this->vars['cssDimensions'] = $this->getCssDimensions();
            $this->vars['relationManageId'] = post('manage_id');
            $this->vars['relationField'] = post('_relation_field');

            return $this->makePartial('config_form');
        }

        throw new ApplicationException('Unable to find file, it may no longer exist');
    }

    /**
     * Commit the changes of the attachment configuration form.
     */
    public function onSaveAttachmentConfig()
    {
        try {
            $fileModel = $this->getRelationModel();
            if (($fileId = post('file_id')) && ($file = $fileModel::find($fileId))) {
                $file->title = post('title');
                $file->description = post('description');
                $file->save();

                return ['displayName' => $file->title ?: $file->file_name];
            }

            throw new ApplicationException('Unable to find file, it may no longer exist');
        }
        catch (Exception $ex) {
            return json_encode(['error' => $ex->getMessage()]);
        }
    }

    /**
     * Checks the current request to see if it is a postback containing a file upload
     * for this particular widget.
     */
    protected function checkUploadPostback()
    {
        if (!($uniqueId = Request::header('X-OCTOBER-FILEUPLOAD')) || $uniqueId != $this->getId()) {
            return;
        }

        try {
            if (!Input::hasFile('file_data')) {
                throw new ApplicationException('File missing from request');
            }

            $fileModel = $this->getRelationModel();
            $uploadedFile = Input::file('file_data');

            $validationRules = ['max:'.$fileModel::getMaxFilesize()];
            if ($fileTypes = $this->getAcceptedFileTypes()) {
                $validationRules[] = 'extensions:'.$fileTypes;
            }

            if ($this->mimeTypes) {
                $validationRules[] = 'mimes:'.$this->mimeTypes;
            }

            $validation = Validator::make(
                ['file_data' => $uploadedFile],
                ['file_data' => $validationRules]
            );

            if ($validation->fails()) {
                throw new ValidationException($validation);
            }

            if (!$uploadedFile->isValid()) {
                throw new ApplicationException('File is not valid');
            }

            $fileRelation = $this->getRelationObject();

            $file = $fileModel;
            $file->data = $uploadedFile;
            $file->is_public = $fileRelation->isPublic();
            $file->save();

            $fileRelation->add($file, $this->sessionKey);

            $file = $this->decorateFileAttributes($file);

            $result = [
                'id' => $file->id,
                'thumb' => $file->thumbUrl,
                'path' => $file->pathUrl
            ];

            Response::json($result, 200)->send();

        }
        catch (Exception $ex) {
            Response::json($ex->getMessage(), 400)->send();
        }

        exit;
    }

    /**
     * Adds the bespoke attributes used internally by this widget.
     * - thumbUrl
     * - pathUrl
     * @return System\Models\File
     */
    protected function decorateFileAttributes($file)
    {
        /*
         * File is protected, create a secure public path
         */
        if (!$file->isPublic()) {
            $path = $thumb = FilesController::getDownloadUrl($file);

            if ($this->imageWidth || $this->imageHeight) {
                $thumb = FilesController::getThumbUrl($file, $this->imageWidth, $this->imageHeight, $this->thumbOptions);
            }
        }
        /*
         * Otherwise use public paths
         */
        else {
            $path = $thumb = $file->getPath();

            if ($this->imageWidth || $this->imageHeight) {
                $thumb = $file->getThumb($this->imageWidth, $this->imageHeight, $this->thumbOptions);
            }
        }

        $file->pathUrl = $path;
        $file->thumbUrl = $thumb;

        return $file;
    }
}
