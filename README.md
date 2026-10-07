# Pesu (பேசு) – English & Tamil AAC board for Laravel

A picture-based talking board for people with speech difficulties. Users tap pictures to build
a sentence and the browser speaks it in English or Tamil. Each user can add their own words and
photos, save phrases, and (optionally) keep a history that parents and therapists can review.

Built for Laravel 11/12 with Laravel Breeze (Blade) for login. No extra Composer packages.

## Setup

```bash
composer create-project laravel/laravel pesu
cd pesu
composer require laravel/breeze --dev
php artisan breeze:install blade
```

Copy the files from this folder into the project, keeping the same paths. These replace Breeze/Laravel
files: `routes/web.php`, `app/Models/User.php`, `database/seeders/DatabaseSeeder.php`.

Add the board's two entry points to the `input` array in your existing `vite.config.js`
(keep whatever else is already there):

```js
input: [
    'resources/css/app.css',
    'resources/js/app.js',
    'resources/css/aac.css',      // add
    'resources/js/aac/app.js',    // add
],
```

Then:

```bash
php artisan migrate --seed      # creates tables + built-in vocabulary + test@example.com / password
php artisan storage:link        # so uploaded photos are viewable
npm install && npm run dev
php artisan serve
```

Open http://localhost:8000, log in as `test@example.com` / `password`, and you'll land on the board.
Set `APP_URL` in `.env` to the address you use so photo URLs are correct.

Run the tests with `php artisan test`.

## How it fits together

| Piece | File |
|---|---|
| Tables | `database/migrations/2026_01_01_00000*_*.php` |
| Built-in vocabulary (edit here) | `database/seeders/AacVocabularySeeder.php` |
| Board page + data | `app/Http/Controllers/BoardController.php`, `resources/views/aac/board.blade.php` |
| JSON endpoints | `app/Http/Controllers/Aac/*` (tiles, categories, phrases, settings, usage) |
| Who may change what | `app/Policies/CategoryPolicy.php`, `app/Policies/TilePolicy.php` |
| Recorded Tamil voices | `resources/js/aac/recorder.js` (recording), `Aac/TileAudioController.php` (playback) |
| Activity page | `app/Http/Controllers/Aac/ActivityController.php`, `resources/views/aac/activity.blade.php` |
| Sentence grammar | `resources/js/aac/grammar.js` |
| Text-to-speech | `resources/js/aac/speech.js` |
| UI | `resources/js/aac/app.js`, `resources/css/aac.css` |

**Tiles** with `user_id = NULL` are built-in and shared; tiles with a `user_id` are words that user
added. **Categories** follow the same rule: users can make their own (✏️ Edit → ＋ New category), which only
they can see. A category's word type sets the colour and grammar of the words in it, and a category can
only be deleted once it is empty, so words are never lost. Re-running `php artisan db:seed --class=AacVocabularySeeder` refreshes the built-in words
without touching anyone's custom words.

## Tamil grammar

Tamil is verb-final and adds case endings, so joining words in tap order doesn't work.
Sentence-starter tiles ("frames") have a slot the next picture fills, and words store the forms they need:

| Tap | English | Tamil |
|---|---|---|
| I want… + water | I want water | எனக்கு தண்ணீர் வேண்டும் |
| I want… + play | I want to play | எனக்கு விளையாட வேண்டும் |
| I want… + park | I want to go to the park | எனக்கு பூங்காவுக்கு போக வேண்டும் |
| Let's go… + park | Let's go to the park | பூங்காவுக்கு போகலாம் |
| Where is… + mom | Where is mom? | அம்மா எங்கே? |

- Places store a dative "to" form in `ta_dative` (பூங்கா → பூங்காவுக்கு).
- Actions store an infinitive in `ta_infinitive` (விளையாடு → விளையாட).
- To add a frame, add a row to `seedCore()` and, if it needs special handling, a case in `slotText()` in `grammar.js`.

**Have a native Tamil speaker or speech therapist review the vocabulary before real use.**

## Speech

Speech uses the browser's Web Speech API, so voices depend on the device. Android (Google Speech
Services → Install voice data → Tamil) and Windows usually have a Tamil voice; some devices don't.
The board warns when no Tamil voice is found.

Users can also record a Tamil pronunciation for their own words (up to 10 seconds, set by `MAX_SECONDS`
in `recorder.js`), and the therapist records the Tamil voice of built-in words (✏️ Edit → tap a word, or
the 🎙 Recordings page). In Tamil:

- **Built-in words** use the device's Tamil voice first; without one (or if it fails) the therapist's recording plays.
- **A parent's own word** plays its recording first, then the device's Tamil voice.
- If neither works, a message says which words couldn't be said.

Sentence starters such as "I want…" use the computer voice, because the Tamil word form changes inside
the sentence. English speech never uses recordings. The app can tell when a device has no Tamil voice,
but not when a voice pronounces a word badly. Recordings are kept on the private `local` disk
(`storage/app/private/aac/audio`, built-in words under `shared/`): every approved user hears the
built-in ones, and a parent's own recordings are heard only by that parent.
Recording needs a microphone and a secure page (https, or localhost).

## Accounts and approval

New sign-ups are parents waiting for approval: they see only a status page until the therapist approves
them on the ✅ Approvals page (rejected accounts are kept, so the same email can't sign up again; they can be
approved later). The therapist registers normally, then an existing account is given therapist rights with
`php artisan pesu:grant-therapist her@email` (it shows the account and asks before changing anything;
`pesu:revoke-therapist` undoes it).

Built-in words are matched by `tiles.seed_key` ("category-slug|English|Tamil"), so re-running the seeder
updates them in place and keeps their ids and recordings. It stops without changing anything if a word
that has a recording was taken out of the list.

## Deploying for free (Vercel + Supabase, no card)

Vercel keeps no files between requests, so the database and files live in Supabase: one free
Supabase project gives Postgres and S3-compatible storage. Vercel runs Laravel through the community
PHP runtime (`vercel.json` → `api/index.php` → `public/index.php`) and serves `public/build` as static
files. During each Vercel build the Composer `vercel` script runs `php artisan migrate --force` and
`php artisan pesu:vocabulary --if-empty` (built-in words into an empty database only; it never runs
`DatabaseSeeder`, which creates a test user). If a migration fails, the build fails and the previous
version stays live. (The `Dockerfile` and `docker/` are an alternative for container hosts such as Render.)

1. **Supabase:** create a project. Turn off the Data API (Pesu doesn't use it). Storage → create bucket
   `aac-recordings` (**private**) and `aac-photos` (**public**). Storage → Settings → S3 access keys →
   create a key. Database → Connect → copy the **Session pooler** connection string.
2. **Vercel:** Add New → Project → import this GitHub repo (Framework preset: Other; `vercel.json`
   sets the build). Add these environment variables for **Production only**, so preview builds of other
   branches never migrate the live database:

   | Variable | Value |
   |---|---|
   | `APP_KEY` | output of `php artisan key:generate --show` (run locally) |
   | `APP_ENV` / `APP_DEBUG` | `production` / `false` |
   | `APP_URL` | `https://<project>.vercel.app` |
   | `TRUSTED_PROXIES` | `*` |
   | `LOG_CHANNEL` | `stderr` |
   | `DB_CONNECTION` / `DB_URL` | `pgsql` / the session pooler string with `?sslmode=require` |
   | `SESSION_DRIVER` / `CACHE_STORE` | `database` / `database` |
   | `SESSION_SECURE_COOKIE` | `true` |
   | `QUEUE_CONNECTION` / `MAIL_MAILER` | `sync` / `log` |
   | `AAC_AUDIO_DISK` / `AAC_PHOTO_DISK` | `aac-recordings` / `aac-photos` |
   | `AAC_S3_ENDPOINT` | `https://<project-ref>.supabase.co/storage/v1/s3` |
   | `AAC_S3_REGION` | the project's region, e.g. `ap-south-1` |
   | `AAC_S3_KEY` / `AAC_S3_SECRET` | the S3 access key pair |
   | `AAC_PHOTOS_URL` | `https://<project-ref>.supabase.co/storage/v1/object/public/aac-photos` |
   | `AAC_AUDIO_CACHE_PATH` | `/tmp/aac-audio` |
   | `APP_CONFIG_CACHE` / `APP_EVENTS_CACHE` | `/tmp/config.php` / `/tmp/events.php` |
   | `APP_PACKAGES_CACHE` / `APP_ROUTES_CACHE` | `/tmp/packages.php` / `/tmp/routes.php` |
   | `APP_SERVICES_CACHE` / `VIEW_COMPILED_PATH` | `/tmp/services.php` / `/tmp` |

3. To make the therapist: she registers, then in Supabase → SQL editor run
   `update users set role = 'therapist', status = 'approved', reviewed_at = now() where email = '…';`

Free plans: Vercel's Hobby plan is for non-commercial use and the first request after a quiet period is
slower; a Supabase project pauses after about a week without use (resume it from the dashboard). PHP on
Vercel depends on the community runtime. Keep backups.

## Privacy

The usage history records the sentences a person speaks, which is sensitive. It can be turned off
per user in Settings and cleared from the Activity page. Get consent from the user or their family
before enabling it, and follow your local data-protection law.

## Ideas for next steps

- Replace emoji with a licensed symbol set (ARASAAC, Mulberry Symbols, OpenSymbols – check each licence).
- Make it a PWA so it works offline.
- Therapist accounts that can manage several users' boards.
- Drag-to-reorder tiles (the `sort_order` column is already there).
