/*
 * File upload form field control
 *
 * TODO: check the list below...
 * Data attributes:
 * - data-control="mediafileupload" - enables the file upload plugin
 * - data-unique-id="XXX" - an optional identifier for multiple uploaders on the same page, this value will 
 *   appear in the postback variable called X_OCTOBER_MEDIAFILEUPLOAD // TODO: Delete this, if not needed (or implement it)
 *   appear in the postback variable called X_OCTOBER_FILEUPLOAD // TODO: why the snake case, when we send this data differently????
 * - data-template - a Dropzone.js template to use for each item
 * - data-error-template - a popover template used to show an error
 * - data-sort-handler - AJAX handler for sorting postbacks
 * - data-config-handler - AJAX handler for configuration popup
 *
 * JavaScript API:
 * $('div').mediaFileUploader()
 *
 * Dependancies:
 * - Dropzone.js
 */
+function ($) { "use strict";

    var Base = $.oc.foundation.base,
        BaseProto = Base.prototype

    // FILEUPLOAD CLASS DEFINITION
    // ============================

    var FileUpload = function (element, options) {
        this.$el = $(element)
        this.options = options || {}

        $.oc.foundation.controlUtils.markDisposable(element)
        Base.call(this)
        this.init()
    }

    FileUpload.prototype = Object.create(BaseProto)
    FileUpload.prototype.constructor = FileUpload

    FileUpload.prototype.init = function() {
        if (this.options.isMulti === null) {
            this.options.isMulti = this.$el.hasClass('is-multi')
        }

        if (this.options.isPreview === null) {
            this.options.isPreview = this.$el.hasClass('is-preview')
        }

        if (this.options.isSortable === null) {
            this.options.isSortable = this.$el.hasClass('is-sortable')
        }

        this.$el.one('dispose-control', this.proxy(this.dispose))
        this.$uploadButton = $('.upload-button', this.$el)
        this.$filesContainer = $('.upload-files-container', this.$el)
        this.uploaderOptions = {}

        this.$el.on('click', '.upload-object.is-success', this.proxy(this.onClickSuccessObject))
        this.$el.on('click', '.upload-object.is-error', this.proxy(this.onClickErrorObject))

        // Stop here for preview mode
        if (this.options.isPreview)
            return

        this.$el.on('click', '.upload-remove-button', this.proxy(this.onRemoveObject))
        this.$el.on('click', '[data-control="convert"]', this.proxy(this.onConvertObject))
        this.$el.on('click', '[data-control="selectframe"]', this.proxy(this.onClickSelectFrameButton));

        this.bindUploader();
        this.bindPreviewUploader();
        
        if (this.options.isSortable) {
            this.bindSortable()
        }
    }

    FileUpload.prototype.dispose = function() {
        
        // remove dropzone
        Dropzone.forElement(this.$el.get(0)).destroy();
        // and button upload too
        this.$el.find('[data-control="upload-preview"]').each(function(){
            if(this.dropzone){
                Dropzone.forElement(this).destroy();
            }
        });
        
        
        this.$el.off('click', '.upload-object.is-success', this.proxy(this.onClickSuccessObject))
        this.$el.off('click', '.upload-object.is-error', this.proxy(this.onClickErrorObject))
        this.$el.off('click', '.upload-remove-button', this.proxy(this.onRemoveObject))
        this.$el.off('click', '[data-control="convert"]', this.proxy(this.onConvertObject))
        this.$el.off('click', '[data-control="selectframe"]', this.proxy(this.onClickSelectFrameButton));

        this.$el.off('dispose-control', this.proxy(this.dispose))
        this.$el.removeData('oc.mediaFileUpload')

        this.$el = null
        this.$uploadButton = null
        this.$filesContainer = null
        this.uploaderOptions = null

        // In some cases options could contain callbacks, 
        // so it's better to clean them up too.
        this.options = null
        
        BaseProto.dispose.call(this)
    }

    //
    // Uploading
    //
    
    FileUpload.prototype.bindUploader = function() {
        this.uploaderOptions = {
            maxFilesize: 1024,
            url: this.options.url,
            paramName: this.options.paramName,
            clickable: this.$uploadButton.get(0),
            previewsContainer: this.$filesContainer.get(0),
            maxFiles: !this.options.isMulti ? 1 : null,
            headers: {}
        }

        if (this.options.fileTypes) {
            this.uploaderOptions.acceptedFiles = this.options.fileTypes
        }

        if (this.options.template) {
            this.uploaderOptions.previewTemplate = $(this.options.template).html()
        }

        if (this.options.uniqueId) {
            this.uploaderOptions.headers['X-OCTOBER-FILEUPLOAD'] = this.options.uniqueId
        }

        this.uploaderOptions.thumbnailWidth = this.options.thumbnailWidth
            ? this.options.thumbnailWidth : null

        this.uploaderOptions.thumbnailHeight = this.options.thumbnailHeight
            ? this.options.thumbnailHeight : null

        //this.uploaderOptions.resize = this.onResizeFileInfo

        /*
         * Add CSRF token to headers
         */
        var token = $('meta[name="csrf-token"]').attr('content')
        if (token) {
            this.uploaderOptions.headers['X-CSRF-TOKEN'] = token
        }

        this.dropzone = new Dropzone(this.$el.get(0), this.uploaderOptions)
        this.dropzone.on('addedfile', this.proxy(this.onUploadAddedFile))
        this.dropzone.on('sending', this.proxy(this.onUploadSending))
        this.dropzone.on('success', this.proxy(this.onUploadSuccess))
        this.dropzone.on('error', this.proxy(this.onUploadError))
        this.dropzone.on('queuecomplete', this.proxy(this.onUploadQueueComplete))
    }
    
    /*
    FileUpload.prototype.onResizeFileInfo = function(file) {
        var info,
            targetWidth,
            targetHeight

        if (!this.options.thumbnailWidth && !this.options.thumbnailWidth) {
            targetWidth = targetHeight = 100
        }
        else if (this.options.thumbnailWidth) {
            targetWidth = this.options.thumbnailWidth
            targetHeight = this.options.thumbnailWidth * file.height / file.width
        }
        else if (this.options.thumbnailHeight) {
            targetWidth = this.options.thumbnailHeight * file.height / file.width
            targetHeight = this.options.thumbnailHeight
        }

        // drawImage(image, srcX, srcY, srcWidth, srcHeight, trgX, trgY, trgWidth, trgHeight) takes an image, clips it to
        // the rectangle (srcX, srcY, srcWidth, srcHeight), scales it to dimensions (trgWidth, trgHeight), and draws it
        // on the canvas at coordinates (trgX, trgY).
        info = {
            srcX: 0,
            srcY: 0,
            srcWidth: file.width,
            srcHeight: file.height,
            trgX: 0,
            trgY: 0,
            trgWidth: targetWidth,
            trgHeight: targetHeight
        }

        return info
    }
    */
    
    FileUpload.prototype.onUploadAddedFile = function(file) {
        var $object = $(file.previewElement).data('dzFileObject', file)

        // Remove any exisiting objects for single variety
        if (!this.options.isMulti) {
            this.removeFileFromElement($object.siblings())
        }

        this.evalIsPopulated()
    }

    FileUpload.prototype.onUploadSending = function(file, xhr, formData) {
        this.addExtraFormData(formData)
    }

    FileUpload.prototype.onUploadSuccess = function(file, response) {
        var $preview = $(file.previewElement),
            $img = $('.item img', $preview)

        $preview.addClass('is-success')

        if (response.id) {
            $preview.data('id', response.id)
            $preview.data('path', response.path)
            $('.upload-remove-button', $preview).data('request-data', { file_id: response.id })
            $img.attr('src', response.thumb)
        }

        /*
         * Trigger change event (Compatability with october.form.js)
         */
        this.$el.closest('[data-field-name]').trigger('change.oc.formwidget')
    }

    FileUpload.prototype.onUploadError = function(file, error) {
        var $preview = $(file.previewElement)
        $preview.addClass('is-error')
    }

    FileUpload.prototype.addExtraFormData = function(formData) {
        if (this.options.extraData) {
            $.each(this.options.extraData, function (name, value) {
                formData.append(name, value)
            })
        }

        var $form = this.$el.closest('form')
        if ($form.length > 0) {
            $.each($form.serializeArray(), function (index, field) {
                formData.append(field.name, field.value)
            })
        }
    }

    FileUpload.prototype.removeFileFromElement = function($element) {
        var self = this

        $element.each(function() {
            var $el = $(this),
                obj = $el.data('dzFileObject')

            if (obj) {
                self.dropzone.removeFile(obj)
            }
            else {
                $el.remove()
            }
        })
    }
    
    FileUpload.prototype.onUploadQueueComplete = function(){
        //this.$el.closest('form').submit();
        this.$el.closest('[data-field-name]').trigger('change.oc.formwidget')
        this.$el.closest('form').trigger('change.oc.formwidget')
    }
    
    //
    // #################################################################################################################################
    // Uploading Preview File
    //
    
    FileUpload.prototype.bindPreviewUploader = function()
    {
        var $btn = this.$el.find('[data-control="upload-preview"]');
        var _self = this;
        $btn.each(function(){
            _self.bindSpecificPreviewUploader(this);
        });
    };
    
    FileUpload.prototype.bindSpecificPreviewUploader = function(btn)
    {
        var $btn = $(btn);
        var postUrl = $btn.data('url');
        var handler = $btn.data('handler');
        var $target = $btn.closest('.upload-object');
        var fileId = $target.data('id');
        
        if(postUrl && (postUrl != '')){
            var uploaderOptions = {
                clickable: btn,
                method: 'POST',
                url: $btn.data('url'),
                paramName: 'preview_data',
                createImageThumbnails: false,
                uploadMultiple: false,
                headers: {
                    'X-OCTOBER-REQUEST-HANDLER': handler
                },
                //previewTemplate: '',
                //previewsContainer: this.$el.find('[data-control="upload-ui"] .upload-preview').get(0)
                // fallback: implement method that would set a flag that the uploader is not supported by the browser
            };
            
            if(!btn.dropzone){
                this.dropzone2 = new Dropzone(btn, uploaderOptions);
                this.dropzone2.on("sending", function(file, xhr, data) {
                    data.append("file_id", fileId);
                });
                this.dropzone2.on('success', this.proxy(this.onPreviewUploadSuccess))
            }
        }
    };
    
    FileUpload.prototype.onPreviewUploadSuccess = function(file,data,progressEvent) { //progressEvent = jqXHR
        /**
         * 
         */
        for (var partial in data) {
            /*
             * If a partial has been supplied on the client side that matches the server supplied key, look up
             * it's selector and use that. If not, we assume it is an explicit selector reference.
             */
            var selector = partial
            if (jQuery.type(selector) == 'string' && selector.charAt(0) == '@') {
                $(selector.substring(1)).append(data[partial]).trigger('ajaxUpdate', [[], data, 'SUCCESS', progressEvent])
            } else if (jQuery.type(selector) == 'string' && selector.charAt(0) == '^') {
                $(selector.substring(1)).prepend(data[partial]).trigger('ajaxUpdate', [[], data, 'SUCCESS', progressEvent])
            } else {
                $(selector).trigger('ajaxBeforeReplace')
                $(selector).html(data[partial]).trigger('ajaxUpdate', [[], data, 'SUCCESS', progressEvent])
            }
        }

        /*
         * Wait for .html() method to finish rendering from partial updates
         */
        setTimeout(function() {
            $(window)
                .trigger('ajaxUpdateComplete', [[], data, 'SUCCESS', progressEvent])
                .trigger('resize')
        }, 0)
    };
    
    //
    // #################################################################################################################################
    // Sorting
    //

    FileUpload.prototype.bindSortable = function() {
        var
            self = this,
            placeholderEl = $('<div class="upload-object upload-placeholder"/>').css({
                //width: this.options.imageWidth,
                //height: this.options.imageHeight
                width: (this.options.thumbnailWidth+300),
                height: this.options.thumbnailHeight
            })

        this.$filesContainer.sortable({
            itemSelector: 'div.upload-object.is-success',
            nested: false,
            tolerance: -100,
            placeholder: placeholderEl,
            handle: '.drag-handle',
            onDrop: function ($item, container, _super) {
                var minWidth = $item.css('minWidth');
                _super($item, container)
                self.onSortAttachments()
                $item.css('minWidth', minWidth);
            },
            distance: 10
        })
    }

    FileUpload.prototype.onSortAttachments = function() {
        if (this.options.sortHandler) {

            /*
             * Build an object of ID:ORDER
             */
            var orderData = {}

            this.$el.find('.upload-object.is-success')
                .each(function(index){
                    var id = $(this).data('id')
                    orderData[id] = index + 1
                })

            this.$el.request(this.options.sortHandler, {
                data: { sortOrder: orderData }
            })
        }
    }

    //
    // User interaction
    //

    FileUpload.prototype.onRemoveObject = function(ev) {
        var self = this,
            $object = $(ev.target).closest('.upload-object')
            
        $(ev.target)
            .closest('.upload-remove-button')
            .one('ajaxPromise', function(){
                $object.addClass('is-loading')
            })
            .one('ajaxDone', function(){
                self.removeFileFromElement($object)
                self.evalIsPopulated()
            })
            .request()

        ev.stopPropagation()
    }
    
    FileUpload.prototype.onConvertObject = function(ev){
        var self = this,
            $object = $(ev.target).closest('.upload-object')
                
        $(ev.target)
            .one('ajaxPromise', function(){
                $object.addClass('is-loading')
            })
            //.one('ajaxDone', function(){
                //alert('done ajax');
                //self.removeFileFromElement($object)
                //self.evalIsPopulated()
            //})
            .request()
        
        ev.stopPropagation()
    }
    
    FileUpload.prototype.onClickSuccessObject = function(ev) {
        /*
         * 
         if ($(ev.target).closest('.meta').length) return

        var $target = $(ev.target).closest('.upload-object')

        if (!this.options.configHandler) {
            window.open($target.data('path'))
            return
        }

        $target.popup({
            handler: this.options.configHandler,
            extraData: { file_id: $target.data('id') }
        })

        $target.one('popupComplete', function(event, element, modal){

            modal.one('ajaxDone', 'button[type=submit]', function(e, context, data) {
                if (data.displayName) {
                    $('[data-dz-name]', $target).text(data.displayName)
                }
            })
        })
        */
    }
    
    //
    // #################################################################################################################################
    // Choose Frame - Popup
    //
        
    /**
     * Choose frame for video cover
     * Available only in video mode !!
     */
    FileUpload.prototype.onClickSelectFrameButton = function(ev)
    {
        var _self = this;
        var $target = $(ev.target).closest('.upload-object');
        var handler = $(ev.target).data('handler');
        
        $target.popup({
            'handler': handler,
            extraData: { file_id: $target.data('id') }
        })
        
        $target.one('shown.oc.popup', function(e, context, $modal)
        {
            var video = $modal.find('video')[0];
            var $timecodeInput = $modal.find('input.timecode');
            
            // DOES NOT WORK WITH FLASH OR SILVERLIGHT
            // set timecode input, on video change
            $(video).on('timeupdate', function(event){
                var timecode = _self.secondsToTimecode(this.currentTime);
                
                if($timecodeInput.val() != timecode){
                    console.log(this.currentTime+' => '+timecode);
                    $timecodeInput.val( timecode );
                }
            });
            
            // DOES NOT WORK WITH FLASH OR SILVERLIGHT
            // update video position, when timecode input has been changed
            $modal.find('input.timecode').on('change', function(){
                var seconds = _self.timecodeToSeconds($(this).val());
                
                if(video.currentTime != seconds){
                    console.log($(this).val()+' => '+seconds);
                    video.currentTime = seconds;
                }
            });
            
            //
            // create frame select video player
            $(video).mediaelementplayer({
                // allows testing on HTML5, flash, silverlight
                // auto: attempts to detect what the browser can do
                // auto_plugin: prefer plugins and then attempt native HTML5
                // native: forces HTML5 playback
                // shim: disallows HTML5, will attempt either Flash or Silverlight
                // none: forces fallback view
                mode: 'native', // because our events do not work with flash or silverlight
                
                videoWidth: '100%',
                videoHeight: '100%',
                enableAutosize: true,
                
                // path to Flash and Silverlight plugins
                pluginPath: $(video).data('pluginPath'),
                // name of flash file
                flashName: 'flashmediaelement-debug.swf',
                // name of silverlight file
                silverlightName: 'silverlightmediaelement.xap'
            });
        });
        
        ev.stopPropagation()
    }
    
    /**
     * Converts seconds (float num) to sexagesimal timecode (HH:MM:SS.MMS)
     * @param {Object} num
     */
    FileUpload.prototype.secondsToTimecode = function(num)
    {
        var sec_num = parseInt(num, 10),
            decimal = parseFloat(num) - sec_num,
            hours   = Math.floor(sec_num / 3600),
            minutes = Math.floor((sec_num - (hours * 3600)) / 60),
            seconds = sec_num - (hours * 3600) - (minutes * 60);

        if (hours   < 10) {hours   = "0"+hours;}
        if (minutes < 10) {minutes = "0"+minutes;}
        if (seconds < 10) {seconds = "0"+seconds;}
        var time    = hours+':'+minutes+':'+seconds;
        time += '.' + (Math.round(decimal*1000) / 1000).toString().substr(2);
        
        // push zero values
        var indexLost = (11 - time.length);
        for(var i=0; i<=indexLost; i++){
            time += '0';
        }
        
        return time;
    }
    
    /**
     * Converts sexagesimal timecode (HH:MM:SS.MMS) or (H:M:S.MS) or (S.MS) to seconds (float num)
     * @param {Object} num
     */
    FileUpload.prototype.timecodeToSeconds = function(numString)
    {
        var lastIndex = numString.length;
        
        // get milliseconds
        var splitMilliSeconds = numString.split(".");
        var milliSeconds = (splitMilliSeconds.length>1) ? splitMilliSeconds[1] : null ;
        
        // get hours, minutes & seconds
        var splitTime = splitMilliSeconds[0].split(":");
        var hours = (splitTime.length>2) ? splitTime[0] : null ;
        var minutes = (splitTime.length>2) ? splitTime[1] : ((splitTime.length>1) ? splitTime[0] : null) ;
        var seconds = (splitTime.length>2) ? splitTime[2] : ((splitTime.length>1) ? splitTime[1] : splitTime[0]) ;
        
        // convert to floating seconds
        var floatSeconds = parseInt(hours) * 60 * 60; // hours
        floatSeconds += parseInt(minutes) * 60; // minutes
        floatSeconds += parseFloat(parseInt(seconds)+'.'+milliSeconds);  // seconds 	
        
        return floatSeconds;
    }
    
    //
    //
    //
    
    FileUpload.prototype.onClickErrorObject = function(ev) {
        var
            self = this,
            $target = $(ev.target).closest('.upload-object'),
            errorMsg = $('[data-dz-errormessage]', $target).text(),
            $template = $(this.options.errorTemplate)

        // Remove any exisiting objects for single variety
        if (!this.options.isMulti) {
            this.removeFileFromElement($target.siblings())
        }

        $target.ocPopover({
            content: Mustache.render($template.html(), { errorMsg: errorMsg }),
            modal: true,
            highlightModalTarget: true,
            placement: 'top',
            fallbackPlacement: 'left',
            containerClass: 'popover-danger'
        })

        var $container = $target.data('oc.popover').$container
        $container.one('click', '[data-remove-file]', function() {
            $target.data('oc.popover').hide()
            self.removeFileFromElement($target)
            self.evalIsPopulated()
        })
    }

    //
    // Helpers
    //

    FileUpload.prototype.evalIsPopulated = function() {
        var isPopulated = !!$('.upload-object', this.$filesContainer).length
        this.$el.toggleClass('is-populated', isPopulated)

        // Reset maxFiles counter
        if (!isPopulated) {
            this.dropzone.removeAllFiles()
        }
    }

    FileUpload.DEFAULTS = {
        url: window.location,
        configHandler: null,
        sortHandler: null,
        uniqueId: null,
        extraData: {},
        paramName: 'file_data',
        fileTypes: null,
        template: null,
        errorTemplate: null,
        isMulti: null,
        isPreview: null,
        isSortable: null,
        thumbnailWidth: 120,
        thumbnailHeight: 120
    }

    // FILEUPLOAD PLUGIN DEFINITION
    // ============================

    var old = $.fn.mediaFileUploader

    $.fn.mediaFileUploader = function (option) {
        return this.each(function () {
            var $this   = $(this)
            var data    = $this.data('oc.mediaFileUpload')
            var options = $.extend({}, FileUpload.DEFAULTS, $this.data(), typeof option == 'object' && option)
            if (!data) $this.data('oc.mediaFileUpload', (data = new FileUpload(this, options)))
            if (typeof option == 'string') data[option].call($this)
        })
    }

    $.fn.mediaFileUploader.Constructor = FileUpload

    // FILEUPLOAD NO CONFLICT
    // =================

    $.fn.mediaFileUploader.noConflict = function () {
        $.fn.mediaFileUpload = old
        return this
    }

    // FILEUPLOAD DATA-API
    // ===============
    $(document).render(function () {
        $('[data-control="mediafileupload"]').mediaFileUploader()
    })

}(window.jQuery);
