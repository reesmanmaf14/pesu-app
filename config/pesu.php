<?php

/*
| Where Pesu keeps uploaded files. Locally these are the "local" (private) and "public" disks.
| On Laravel Cloud the filesystem is reset on every deploy, so attach buckets and set these to the
| bucket disk names chosen in the Cloud dashboard: a PRIVATE bucket for recordings (heard only through
| TileAudioController's permission check) and a PUBLIC bucket for word photos.
*/
return [

    'audio_disk' => env('AAC_AUDIO_DISK', 'local'),

    'photo_disk' => env('AAC_PHOTO_DISK', 'public'),

    // Where recordings from a bucket are cached on the server before being played. It must be writable;
    // on Vercel only /tmp is, so set AAC_AUDIO_CACHE_PATH=/tmp/aac-audio there.
    'audio_cache_path' => env('AAC_AUDIO_CACHE_PATH', storage_path('framework/cache/aac-audio')),

];
