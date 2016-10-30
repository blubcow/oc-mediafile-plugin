# Media Files for OctoberCMS
- [Content](#content)
- [Setup](#setup)
- [Additional Setup Steps](#additional-setup-steps)

## Content
This package contains a replacement class for the file attachments.  
Also, a backend form widget is included, as are some console commands.  
For some functions to work you must have ffmpeg installed and the path added to our config file.

## Setup

#### Add ffmpeg config value
- Either override `ffmpegPath` in the plugin config file in `config/sewa/mediafile/config.php`
- Or call `MediaFileManager::instance()->setFfmpegPath( 'C:\\ffmpeg.exe' )`

#### Add Attachments to your Model
```
public $attachOne = [
    'audio_file' => ['Sewa\Mediafile\Models\MediaFile']
];
public $attachMany = [
    'video_files' => ['Sewa\Mediafile\Models\MediaFile']
];
```

#### Add Form Widget to your Backend Form
```
fields:
    video_files:
        label: Video
        type: mediafileupload
        mode: video-multi
        conversionTypes: ['mp4','webm','ogg']
        imageWidth: 200
        imageHeight: 200
    audio_file:
        label: Audio
        type: mediafileupload
        mode: audio-single
        conversionTypes: ['mp3']
        imageWidth: 200
        imageHeight: 200
```

## Additional Setup Steps

##### Add recognised types to `.htaccess` - `Line 1`
```
AddType video/ogg .ogv
AddType video/mp4 .mp4
AddType video/webm .webm
```

##### Add extensions to `.htaccess` whitelist - `Line 65`
```
RewriteCond %{REQUEST_URI} !\.xap
RewriteCond %{REQUEST_URI} !\.ogv
```

##### Media Display Code
```
<video width="100%" height="auto" controls="controls" preload="auto">
    <!-- MP4 for Safari, IE9, iPhone, iPad, Android, and Windows Phone 7 -->
    <?php if($file->hasConvertedFile('mp4')): ?><source type="video/mp4" src="<?= $file->getConvertedPath('mp4') ?>" /><?php endif ?>
    <!-- WebM/VP8 for Firefox4, Opera, and Chrome -->
    <?php if($file->hasConvertedFile('webm')): ?><source type="video/webm" src="<?= $file->getConvertedPath('webm') ?>" /><?php endif ?>
    <!-- Ogg/Vorbis for older Firefox and Opera versions -->
    <?php if($file->hasConvertedFile('ogg')): ?><source type="video/ogg" src="<?= $file->getConvertedPath('ogg') ?>" /><?php endif ?>
    <!-- Flash fallback for non-HTML5 browsers without JavaScript -->
    <object width="320" height="240" type="application/x-shockwave-flash" data="plugins/sewa/mediafile/assets/mediaelement-js/flashmediaelement.swf">
        <?php if($file->hasConvertedFile('mp4')): ?>
            <param name="movie" value="plugins/sewa/mediafile/assets/mediaelement-js/flashmediaelement.swf" />
            <param name="flashvars" value="controls=true&file=<?= $file->getConvertedPath('mp4') ?>" />
        <?php endif ?>
    </object>
</video>
```