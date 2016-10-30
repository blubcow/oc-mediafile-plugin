# Media Files for OctoberCMS
- [Content](#content)
- [Setup](#setup)
- [Additional Setup Steps](#additional-setup-steps)

## Content
This package contains two backend lists to replace the default ones.  
This plugin is based on the backend `List` widget `Backend\Widgets\Lists`  
and the backend `ListController` behaviour `Backend\Behaviors\ListController`.

## Setup

#### Add ffmpeg config value
- Either override `ffmpegPath` in the plugin config file in `config/sewa/mediafile/config.php`
- Or call `MediaFileManager::instance()->setFfmpegPath( 'C:\\ffmpeg.exe' )`

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