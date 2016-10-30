<?php

return [
    
    // Path to ffmpeg. Needs to be callable in console.
    // NULL will throw an error
    'ffmpegPath' => null,
    
    // Set the operating system. Possible values are:
    // 'win', 'mac', 'linux' or 'auto'
    // NULL is the same as 'auto'
    'system' => 'auto',
    
    // Available conversion settings
    'conversionTypes' => [
        'mp4' => [
            'suffix' => '_converted',
            'extension' => '.mp4',
            'ffmpegOptions' => '-c:v libx264 -r 24 -g 12 -crf 23 -maxrate 1000k -bufsize 1536k -ac 2 -c:a aac -vbr 4 -strict -2'
            // AUDIO:
            // -vbr – Target a quality, rather than a specific bit rate. 1 is lowest quality and 5 is highest quality
            // [aac @ 0033dd00] The encoder 'aac' is experimental but experimental codecs are not enabled, add '-strict -2' if you want to use it.
        ],
        'webm' => [
            'suffix' => '_converted',
            'extension' => '.webm',
            'ffmpegOptions' => '-c:v libvpx -r 24 -g 12 -crf 23 -b:v 1000k -deadline good -cpu-used 5 -ac 2 -c:a libvorbis -qscale:a 3'
        ],
        'ogg' => [
            'suffix' => '_converted',
            'extension' => '.ogg',
            'ffmpegOptions' => '-c:v libtheora -r 24 -g 12 -qscale:v 5 -ac 2 -c:a libvorbis -qscale:a 3'
            // -qscale:v – video quality. Range is 0–10, where 10 is highest quality. 5–7 is a good range to try
            // -qscale:a – audio quality. Range is 0–10, where 10 is highest quality. 3–6 is a good range to try. Default is -qscale:a 3
        ],
        'mp3' => [
            'suffix' => '_converted',
            'extension' => '.mp3',
            'ffmpegOptions' => '-vn -ar 44100 -ac 2 -ab 192k -f mp3'
        ]
    ]
];