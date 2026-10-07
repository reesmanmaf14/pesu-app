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

];
