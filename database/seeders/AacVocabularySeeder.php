<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Tile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Built-in English + Tamil vocabulary. Safe to re-run: each built-in tile (user_id = NULL) is matched
 * by its seed_key and updated in place, so it keeps its id and the therapist's Tamil recording
 * (ta_audio_path is never written here). Words that users added themselves are never touched.
 *
 * A word's seed_key is "category-slug|English label|Tamil label". To change a word's labels but keep
 * its recording, give it the old key: ['🙋', 'I', 'எனக்கு', 'key' => 'quick|I|எனக்கு'].
 *
 * Have a native Tamil speaker / speech therapist review this list before real use.
 */
class AacVocabularySeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasColumn('tiles', 'seed_key')) {
            throw new RuntimeException('Run "php artisan migrate" first: the tiles table has no seed_key column yet.');
        }

        // Check everything before writing anything.
        $keys = $this->seedKeys($this->categories());

        $removed = Tile::whereNull('user_id')
            ->where(fn ($q) => $q->whereNull('seed_key')->orWhereNotIn('seed_key', $keys))
            ->get();

        $recorded = $removed->filter(fn (Tile $t) => $t->ta_audio_path !== null);
        if ($recorded->isNotEmpty()) {
            throw new RuntimeException(
                "Nothing was changed. These built-in words are no longer in the seeder but have a Tamil recording:\n"
                .$recorded->map(fn (Tile $t) => "  #{$t->id} {$t->label_en} / {$t->label_ta} ({$t->seed_key})")->implode("\n")
                ."\nPut them back, give the renamed word its old 'key', or remove the recording first."
            );
        }

        DB::transaction(function () use ($removed) {
            foreach ($this->categories() as $order => $cat) {
                $this->saveCategory($cat, $order);
            }

            // Words taken out of the list (none of them has a recording, checked above).
            $removed->each(function (Tile $tile) {
                $tile->deleteFiles();
                $tile->delete();
            });
        });
    }

    public static function seedKey(string $slug, array $t): string
    {
        return $t['key'] ?? "{$slug}|{$t[1]}|{$t[2]}";
    }

    /** Every built-in word's seed_key. Stops if two words would share one. */
    private function seedKeys(array $categories): array
    {
        $keys = [];
        $walk = function (array $cats) use (&$walk, &$keys) {
            foreach ($cats as $cat) {
                foreach ($cat['tiles'] as $t) {
                    $key = self::seedKey($cat['slug'], $t);
                    if (isset($keys[$key])) {
                        throw new RuntimeException("Nothing was changed. Two built-in words share the seed key \"{$key}\".");
                    }
                    $keys[$key] = true;
                }
                $walk($cat['children'] ?? []);
            }
        };
        $walk($categories);

        return array_keys($keys);
    }

    /** Save a built-in category, its words and its subcategories. */
    private function saveCategory(array $cat, int $order, ?Category $parent = null): void
    {
        $category = Category::updateOrCreate(
            ['slug' => $cat['slug']],
            [
                'parent_id' => $parent?->id,
                'emoji' => $cat['emoji'],
                'name_en' => $cat['en'],
                'name_ta' => $cat['ta'],
                'default_kind' => $cat['kind'],
                'sort_order' => $order,
            ]
        );

        foreach ($cat['tiles'] as $i => $t) {
            $key = self::seedKey($cat['slug'], $t);
            $tile = Tile::whereNull('user_id')->where('seed_key', $key)->first() ?? new Tile;

            // Only the word's own fields; ta_audio_path (the recording) and user_id are left as they are.
            // Unchanged words are not written at all, so their updated_at stays the same too.
            $tile->forceFill([
                'seed_key' => $key,
                'category_id' => $category->id,
                'kind' => $t['kind'] ?? $cat['kind'],
                'emoji' => $t[0],
                'label_en' => $t[1],
                'label_ta' => $t[2],
                'ta_dative' => $t['dat'] ?? null,
                'ta_infinitive' => $t['inf'] ?? null,
                'en_to' => $t['to'] ?? null,
                'en_infinitive' => $t['enInf'] ?? null,
                'en_article' => $t['the'] ?? false,
                'frame_key' => $t['frame'][0] ?? null,
                'template_en' => $t['frame'][1] ?? null,
                'template_ta' => $t['frame'][2] ?? null,
                'accepts' => $t['frame'][3] ?? null,
                'sort_order' => $i,
            ])->save();
        }

        foreach ($cat['children'] ?? [] as $i => $child) {
            $this->saveCategory($child, $i, $category);
        }
    }

    /** A sentence-starter tile; its label shows the slot as "…". */
    private static function frame(string $key, string $emoji, string $en, string $ta, string $kind, array $accepts): array
    {
        return [
            $emoji, str_replace('{}', '…', $en), str_replace('{}', '…', $ta),
            'kind' => $kind, 'frame' => [$key, $en, $ta, $accepts],
        ];
    }

    /** Protected so tests can seed a changed list. */
    protected function categories(): array
    {
        $objects = ['noun', 'person', 'place', 'verb'];

        return [
            ['slug' => 'quick', 'emoji' => '💬', 'en' => 'Quick talk', 'ta' => 'விரைவு', 'kind' => 'phrase', 'tiles' => [
                // Single high-frequency words. Kept as 'phrase' so frames never take them as a slot filler.
                // Two separate "I" tiles: எனக்கு for wants/needs, நான் as the subject.
                ['🙋', 'I', 'எனக்கு'],
                ['🤲', 'Want', 'வேணும்'],
                ['🙅', "Don't want", 'வேணாம்'],
                ['🧍', 'I', 'நான்'],
                ['👉', 'You', 'நீ'],

                // Sentence starters: the {} slot is filled by the next picture tapped.
              
                // தேவை only reads naturally after a thing or person, so "I need" takes no places or actions.
                ['🫴', 'need', 'தேவை'],
               
                self::frame('go', '🚶', "Let's go {}", '{} போவோம்', 'frame', ['place', 'verb']),
                ['😍', 'like', 'பிடிக்கும்'],
                ['🙅', "don't like", 'பிடிக்காது'],
                ['❓', 'Where?', 'எங்கே?'],

                // Essential words.
                ['✅', 'yes', 'ஆம்', 'kind' => 'social'],
                ['❌', 'no', 'இல்லை', 'kind' => 'neg'],
                ['🆘', 'help', 'உதவி', 'kind' => 'verb'],
                ['✋', 'stop', 'நிறுத்து', 'kind' => 'neg'],
                ['🙏', 'please', 'தயவுசெய்து', 'kind' => 'social'],
                ['➕', 'more', 'இன்னும்', 'kind' => 'adj'],
                ['🏁', 'finished', 'முடிஞ்சு', 'kind' => 'social'],

                // Greetings and manners.
                ['👋', 'Hello', 'வணக்கம்'],
                ['🙏', 'Thank you', 'நன்றி'],
                ['😔', 'Sorry', 'மன்னிக்கவும்'],
                ['👋', 'Bye', 'போய் வாரேன்'],

                // Ready-made phrases.
               
               
                ['🤔', "I don't understand", 'விளங்கல'],
                ['🔁', 'Say it again', 'திரும்பி சொல்லு'],
               
               
            ]],
            ['slug' => 'feelings', 'emoji' => '😊', 'en' => 'Feelings', 'ta' => 'உணர்வுகள்', 'kind' => 'feel', 'tiles' => [
                ['😊', 'Happy', 'மகிழ்ச்சி'],
                ['😢', 'Sad', 'கவலை'],
                ['😠', 'Angry', 'கோபம்'],
                ['😨', 'Scared', 'பயம்'],
                ['😴', 'Tired', 'களைப்பு'],
                ['🍽️', 'Hungry', 'பசி'],
                ['🥤', 'Thirsty', 'தாகம்'],
                ['🤕', 'It hurts', 'நோகுது'],
                ['🤢', 'Sick', 'வருத்தம்'],
                ['😴', 'Sleepy', 'நித்திரை'],
           
            ]],
            // Food & drink holds the food phrases; the foods themselves sit in its subcategories.
            ['slug' => 'food', 'emoji' => '🍚', 'en' => 'Food & drink', 'ta' => 'உணவு', 'kind' => 'noun', 'tiles' => [
                ['🙋', 'I', 'எனக்கு', 'kind' => 'phrase'],
                ['🍽️', 'Hungry', 'பசிக்குது', 'kind' => 'phrase'],
                ['🤲', 'Want', 'வேணும்', 'kind' => 'phrase'],
                ['🙅', "Don't want", 'வேணாம்', 'kind' => 'phrase'],
                ['😍', 'Like', 'பிடிக்கும்', 'kind' => 'phrase'],
                ['🙅', "Don't like", 'பிடிக்காது', 'kind' => 'phrase'],
                ['➕', 'More', 'இன்னும் வேணும்', 'kind' => 'phrase'],
                ['✋', 'Enough', 'போதும்', 'kind' => 'phrase'],
                ['🥄', 'Feed me', 'ஊட்டி விடு', 'kind' => 'phrase'],
                ['🧼', 'Wash hands', 'கை கழுவு', 'kind' => 'phrase'],
                ['😋', 'Tasty', 'சுவையா இருக்கு', 'kind' => 'phrase'],
                ['😖', 'Not tasty', 'சுவையா இல்லை', 'kind' => 'phrase'],
                ['🔥', 'Hot', 'சூடா இருக்கு', 'kind' => 'phrase'],
                ['🌶️', 'Spicy', 'உரப்பு', 'kind' => 'phrase'],
            ], 'children' => [
                ['slug' => 'food-vegetables', 'emoji' => '🥦', 'en' => 'Vegetables', 'ta' => 'காய்கறி', 'kind' => 'noun', 'tiles' => [
                    ['🥕', 'carrot', 'கரட்'],
                    ['🥔', 'potato', 'உருளைக்கிழங்கு'],
                    ['🍅', 'tomato', 'தக்காளி'],
                    ['🧅', 'onion', 'வெங்காயம்'],
                    ['🥒', 'cucumber', 'வெள்ளரிக்காய்'],
                    ['🫘', 'beans', 'பீன்ஸ்'],
                    ['🍆', 'Brinjal', 'கத்தரிக்காய்'],
                    ['🎃', 'Pumpkin', 'பூசணிக்காய்'],
                    ['🥬', 'Spinach', 'கீரை'],
                     ['🫜', 'Beetroot', 'பீட்ரூட்'],
                ]],
                ['slug' => 'food-fruits', 'emoji' => '🍎', 'en' => 'Fruits', 'ta' => 'பழங்கள்', 'kind' => 'noun', 'tiles' => [
                    ['🍌', 'banana', 'வாழைப்பழம்'],
                    ['🍎', 'apple', 'ஆப்பிள்'],
                    ['🥭', 'mango', 'மாம்பழம்'],
                    ['🍊', 'orange', 'ஆரஞ்சு'],
                    ['🍉', 'watermelon', 'தர்பூசணி'],
                    ['🍇', 'grapes', 'திராட்சை'],
                    ['🍍', 'Pineapple', 'அன்னாசி'],
                    ['🍐', 'Guava', 'கொய்யா'],
                    ['🍈', 'Jackfruit', 'பலாப்பழம்'],
                    ['🍓', 'Strawberry', 'ஸ்ட்ராபெரி'],
                    ['🌴', 'Dates', 'ஈச்சம்பழம்'],
                ]],
                ['slug' => 'food-meals', 'emoji' => '🍛', 'en' => 'Meals', 'ta' => 'சாப்பாடு', 'kind' => 'noun', 'tiles' => [
                    ['🍚', 'rice', 'சோறு'],
                    ['🫓', 'dosa', 'தோசை'],
                    ['🍞', 'bread', 'ரொட்டி'],
                    ['🍛', 'curry', 'கறி'],
                    ['🍜', 'noodles', 'நூடுல்ஸ்'],
                    ['🍲', 'soup', 'சூப்'],
                    ['🥚', 'egg', 'முட்டை'],
                    ['🥞', 'Hopper', 'அப்பம்'],
                    ['🍜', 'String hoppers', 'இடியப்பம்'],
                    ['🍚', 'Pittu', 'பிட்டு'],
                    ['🫓', 'Roti', 'ரொட்டி'],
                    ['🍲', 'Kottu', 'கொத்து'],
                    ['🥣', 'Porridge', 'கஞ்சி'],
                    ['🫘', 'Dhal curry', 'பருப்பு'],
                    ['🐟', 'Fish', 'மீன்'],
                ]],
                ['slug' => 'food-drinks', 'emoji' => '🥤', 'en' => 'Drinks', 'ta' => 'குடி பாணம்', 'kind' => 'noun', 'tiles' => [
                    ['💧', 'water', 'தண்ணீர்'],
                    ['🥛', 'milk', 'பால்'],
                    ['☕', 'tea', 'டீ'],
                    ['🧃', 'juice', 'ஜூஸ்'],
                    ['☕', 'Coffee', 'காப்பி'],
                    ['🥥', 'Coconut water', 'இளநீர்'],
                    ['🥤', 'Milo', 'மைலோ'],
                ]],
                ['slug' => 'food-snacks', 'emoji' => '🍪', 'en' => 'Snacks', 'ta' => 'நொறுக்குத்தீனி', 'kind' => 'noun', 'tiles' => [
                    ['🍪', 'biscuit', 'பிஸ்கட்'],
                    ['🍿', 'popcorn', 'பாப்கார்ன்'],
                    ['🍟', 'chips', 'சிப்ஸ்'],
                ]],
                ['slug' => 'food-desserts', 'emoji' => '🍰', 'en' => 'Desserts', 'ta' => 'இனிப்பு', 'kind' => 'noun', 'tiles' => [
                    ['🍦', 'ice cream', 'ஐஸ்கிரீம்'],
                    ['🍰', 'cake', 'கேக்'],
                    ['🍫', 'chocolate', 'சாக்லேட்'],
                    ['🍮', 'Pudding', 'புட்டிங்'],
                    
                ]],
            ]],
            ['slug' => 'people', 'emoji' => '👪', 'en' => 'People', 'ta' => 'மனிதர்கள்', 'kind' => 'person', 'tiles' => [
                ['👩', 'mom', 'அம்மா'],
                ['👨', 'dad', 'அப்பா'],
                ['👦', 'big brother', 'அண்ணா'],
                ['👧', 'big sister', 'அக்கா'],
                ['👨', 'Uncle', 'மாமா'],
                ['👩', 'Aunt', 'மாமி'],
                ['👵', 'grandma', 'பாட்டி'],
                ['👴', 'grandpa', 'தாத்தா'],
                ['🧑‍🏫', 'teacher', 'ஆசிரியர்'],
                ['🧑‍🤝‍🧑', 'friend', 'நண்பர்'],
                ['🧑‍⚕️', 'doctor', 'மருத்துவர்'],
            ]],
            ['slug' => 'places', 'emoji' => '🏠', 'en' => 'Places', 'ta' => 'இடங்கள்', 'kind' => 'place', 'tiles' => [
                ['🏠', 'home', 'வீடு', 'dat' => 'வீட்டுக்கு', 'to' => 'home'],
                ['🏫', 'school', 'பாடசாலை ', 'dat' => 'பாடசாலைக்கு', 'to' => 'to school'],
                ['🏫', 'classroom', 'வகுப்பறை', 'dat' => 'வகுப்பறைக்கு', 'to' => 'to the classroom', 'the' => true],
                ['🏖️', 'beach', 'கடற்கரை', 'dat' => 'கடற்கரைக்கு', 'to' => 'to the beach', 'the' => true],
                ['🌳', 'park', 'பூங்கா', 'dat' => 'பூங்காவுக்கு', 'to' => 'to the park', 'the' => true],
                ['🚻', 'toilet', 'கழிப்பறை', 'dat' => 'கழிப்பறைக்கு', 'to' => 'to the toilet', 'the' => true],
                ['🏪', 'shop', 'கடை', 'dat' => 'கடைக்கு', 'to' => 'to the shop', 'the' => true],
                ['🕌', 'mosque', 'பள்ளிவாசல்', 'dat' => 'பள்ளிவாசலுக்கு', 'to' => 'to the mosque'],
                ['🏥', 'hospital', 'மருத்துவமனை', 'dat' => 'மருத்துவமனைக்கு', 'to' => 'to the hospital', 'the' => true],
                ['🛕', 'temple', 'கோயில்', 'dat' => 'கோயிலுக்கு', 'to' => 'to the temple', 'the' => true],
                ['🚪', 'outside', 'வெளியே', 'dat' => 'வெளியே', 'to' => 'outside'],
            ]],
            ['slug' => 'actions', 'emoji' => '🏃', 'en' => 'Actions', 'ta' => 'செயல்கள்', 'kind' => 'verb', 'tiles' => [
                ['🍛', 'eat', 'சாப்பிடு', 'inf' => 'சாப்பிட'],
                ['🥤', 'drink', 'குடி', 'inf' => 'குடிக்க'],
                ['⚽', 'play', 'விளையாடு', 'inf' => 'விளையாட'],
                ['🛏️', 'sleep', 'தூங்கு', 'inf' => 'தூங்க'],
                ['🛁', 'bath', 'குளி', 'inf' => 'குளிக்க', 'enInf' => 'to have a bath'],
                ['📖', 'read', 'படி', 'inf' => 'படிக்க'],
                ['👀', 'look', 'பார்', 'inf' => 'பார்க்க'],
                ['🎧', 'listen', 'கேள்', 'inf' => 'கேட்க'],
                ['🖍️', 'draw', 'வரை', 'inf' => 'வரைய'],
                ['🚶', 'walk', 'நட', 'inf' => 'நடக்க'],
                ['🪑', 'sit', 'உட்கார்', 'inf' => 'உட்கார'],
                ['👉', 'come', 'வா', 'inf' => 'வர'],
                ['🏃', 'run', 'ஓடு', 'inf' => 'ஓட'],
                ['🚶', 'go', 'போ', 'inf' => 'போக'],
                ['🧍', 'stand', 'நில்', 'inf' => 'நிற்க'],
                ['🤝', 'give', 'கொடு', 'inf' => 'கொடுக்க'],
                ['🙌', 'take', 'எடு', 'inf' => 'எடுக்க'],
                ['📦', 'bring', 'கொண்டுவா', 'inf' => 'கொண்டுவர'],
                ['🗣️', 'talk', 'பேசு', 'inf' => 'பேச'],
                ['❓', 'ask', 'கேள்', 'inf' => 'கேட்க'],
                ['✍️', 'write', 'எழுது', 'inf' => 'எழுத'],
                ['✂️', 'cut', 'வெட்டு', 'inf' => 'வெட்ட'],
                ['😊', 'smile', 'சிரி', 'inf' => 'சிரிக்க'],
                ['👏', 'clap', 'கை தட்டு', 'inf' => 'கை தட்ட'],
            ]],
            ['slug' => 'describe', 'emoji' => '🎨', 'en' => 'Describe', 'ta' => 'விவரிக்க', 'kind' => 'adj', 'tiles' => [
                ['🐘', 'big', 'பெரியது'],
                ['🐜', 'small', 'சிறியது'],
                ['🔥', 'hot', 'சூடு'],
                ['🧊', 'cold', 'குளிர்'],
                ['👍', 'good', 'நல்லது'],
                ['👎', 'bad', 'கெட்டது'],
                ['🐇', 'fast', 'வேகமாக'],
                ['🐢', 'slow', 'மெதுவாக'],
                ['🔊', 'loud', 'சத்தம்'],
                ['🤫', 'quiet', 'அமைதி'],
            ]],
            ['slug' => 'numbers', 'emoji' => '🔢', 'en' => 'Numbers', 'ta' => 'எண்கள்', 'kind' => 'adj', 'tiles' => [
                ['0️⃣', 'zero', 'பூச்சியம்'],
                ['1️⃣', 'one', 'ஒன்று'],
                ['2️⃣', 'two', 'இரண்டு'],
                ['3️⃣', 'three', 'மூன்று'],
                ['4️⃣', 'four', 'நான்கு'],
                ['5️⃣', 'five', 'ஐந்து'],
                ['6️⃣', 'six', 'ஆறு'],
                ['7️⃣', 'seven', 'ஏழு'],
                ['8️⃣', 'eight', 'எட்டு'],
                ['9️⃣', 'nine', 'ஒன்பது'],
                ['🔟', 'ten', 'பத்து'],

                // No keycap emoji exists above 10, so the digits themselves are the picture.
                ['11', 'eleven', 'பதினொன்று'],
                ['12', 'twelve', 'பன்னிரண்டு'],
                ['13', 'thirteen', 'பதின்மூன்று'],
                ['14', 'fourteen', 'பதினான்கு'],
                ['15', 'fifteen', 'பதினைந்து'],
                ['16', 'sixteen', 'பதினாறு'],
                ['17', 'seventeen', 'பதினேழு'],
                ['18', 'eighteen', 'பதினெட்டு'],
                ['19', 'nineteen', 'பத்தொன்பது'],
                ['20', 'twenty', 'இருபது'],
                ['30', 'thirty', 'முப்பது'],
                ['40', 'forty', 'நாற்பது'],
                ['50', 'fifty', 'ஐம்பது'],
                ['60', 'sixty', 'அறுபது'],
                ['70', 'seventy', 'எழுபது'],
                ['80', 'eighty', 'எண்பது'],
                ['90', 'ninety', 'தொண்ணூறு'],
                ['100', 'one hundred', 'நூறு'],
                ['🔢', 'How many?', 'எத்தனை?', 'kind' => 'phrase'],
            ]],
           
            ['slug' => 'clothes', 'emoji' => '👕', 'en' => 'Clothes', 'ta' => 'உடுப்புகள்', 'kind' => 'noun', 'tiles' => [
                ['👕', 'shirt', 'சட்டை'],
                ['👚', 'blouse', 'பிளவுஸ்'],
                ['👖', 'pants', 'காற்சட்டை'],
                ['🩳', 'shorts', 'ஷார்ட்ஸ்'],
                ['👗', 'dress', 'கவுண்'],
                ['🧦', 'socks', 'சொக்ஸ்'],
                ['👟', 'shoes', 'சப்பாத்து'],
                ['🩴', 'slippers', 'செருப்பு'],
                ['🧢', 'cap', 'தொப்பி'],
                ['👕', 'put on', 'போடு', 'kind' => 'verb', 'inf' => 'போட'],
                ['🫳', 'take off', 'கழட்டு', 'kind' => 'verb', 'inf' => 'கழட்ட'],
                ['🔄', 'Change clothes', 'உடுப்பு மாத்தவேணும்', 'kind' => 'phrase'],
                ['🙋', 'My clothes', 'என்ட உடுப்பு', 'kind' => 'phrase'],
                ['🤲', 'I want this', 'இது வேணும்', 'kind' => 'phrase'],
                ['🙅', "I don't want this", 'இது வேணாம்', 'kind' => 'phrase'],
                ['😣', 'Too tight', 'இறுக்கமா இருக்கு', 'kind' => 'phrase'],
                ['👖', 'Too loose', 'லூஸா இருக்கு', 'kind' => 'phrase'],
                ['🟫', 'Dirty', 'ஊத்தையா இருக்கு', 'kind' => 'phrase'],
                ['✨', 'Clean', 'சுத்தமா இருக்கு', 'kind' => 'phrase'],
            ]],
            ['slug' => 'body', 'emoji' => '🧍', 'en' => 'Body', 'ta' => 'உடம்பு', 'kind' => 'noun', 'tiles' => [
                ['🙆', 'head', 'தலை'],
                ['💇', 'hair', 'முடி'],
                ['👁️', 'eye', 'கண்'],
                ['👂', 'ear', 'காது'],
                ['👃', 'nose', 'மூக்கு'],
                ['👄', 'mouth', 'வாய்'],
                ['👅', 'tongue', 'நாக்கு'],
                ['🦷', 'teeth', 'பல்'],
                ['🙂', 'face', 'முகம்'],
                ['✋', 'hand', 'கை'],
                ['☝️', 'finger', 'விரல்'],
                ['💅', 'nail', 'நகம்'],
                ['🦵', 'leg', 'கால்'],
                ['🦶', 'foot', 'பாதம்'],
                ['👆', 'touch', 'தொடு', 'kind' => 'verb', 'inf' => 'தொட'],
                ['🚫', "Don't touch", 'தொடாதே', 'kind' => 'neg'],
                ['👉', 'Show me', 'காட்டு', 'kind' => 'phrase'],
            ]],
            ['slug' => 'animals', 'emoji' => '🐶', 'en' => 'Animals', 'ta' => 'மிருகங்கள்', 'kind' => 'noun', 'tiles' => [
                ['🐕', 'dog', 'நாய்'],
                ['🐈', 'cat', 'பூனை'],
                ['🐄', 'cow', 'மாடு'],
                ['🐃', 'buffalo', 'எருமை'],
                ['🐐', 'goat', 'ஆடு'],
                ['🐑', 'sheep', 'செம்மறியாடு'],
                ['🐎', 'horse', 'குதிரை'],
                ['🐘', 'elephant', 'யானை'],
                ['🦁', 'lion', 'சிங்கம்'],
                ['🐅', 'tiger', 'புலி'],
                ['🐒', 'monkey', 'குரங்கு'],
                ['🐇', 'rabbit', 'முயல்'],
                ['🦌', 'deer', 'மான்'],
                ['🐍', 'snake', 'பாம்பு'],
                ['🐢', 'turtle', 'ஆமை'],
                ['🐊', 'crocodile', 'முதலை'],
                ['🐦', 'bird', 'பறவை'],
                ['🐔', 'chicken', 'கோழி'],
                ['🐓', 'rooster', 'சேவல்'],
                ['🦆', 'duck', 'வாத்து'],
                ['🦅', 'eagle', 'கழுகு'],
                ['🦜', 'parrot', 'கிளி'],
                ['🦉', 'owl', 'ஆந்தை'],
                ['🐦‍⬛', 'crow', 'காகம்'],
            ]],
            ['slug' => 'washroom', 'emoji' => '🚻', 'en' => 'Washroom', 'ta' => 'கழிப்பறை', 'kind' => 'noun', 'tiles' => [
                // First tile so it is always the quickest to reach.
                ['🚿', 'shower', 'ஷவர்'],
                ['🧼', 'soap', 'சவர்க்காரம்'],
                ['🧴', 'shampoo', 'ஷாம்பு'],
                ['🪥', 'toothbrush', 'பிரஷ்'],
                ['🪣', 'bucket', 'வாளி'],
                ['🧖', 'towel', 'துவாய்'],
                ['🪞', 'mirror', 'கண்ணாடி'],
                ['🚰', 'tap', 'குழாய்'],
                ['🚿', 'Take a shower', 'குளிக்கணும்', 'kind' => 'phrase'],
                ['💦', 'wash', 'கழுவு', 'kind' => 'verb', 'inf' => 'கழுவ'],
                ['💧', 'Wash face', 'முகம் கழுவு', 'kind' => 'phrase'],
                ['🪥', 'Brush teeth', 'பல் துலக்கு', 'kind' => 'phrase'],
                ['🧼', 'Use soap', 'சவர்க்காரம் போடு', 'kind' => 'phrase'],
                ['🧽', 'wipe', 'துடை', 'kind' => 'verb', 'inf' => 'துடைக்க'],
            ]],
            ['slug' => 'colors', 'emoji' => '🌈', 'en' => 'Colors', 'ta' => 'நிறங்கள்', 'kind' => 'adj', 'tiles' => [
                ['🔴', 'red', 'சிவப்பு'],
                ['🔵', 'blue', 'நீலம்'],
                ['🟢', 'green', 'பச்சை'],
                ['🟡', 'yellow', 'மஞ்சள்'],
                ['🟠', 'orange', 'ஆரஞ்சு'],
                // 🩷 and 🩶 are too new for older tablets, so pink and grey use familiar pictures.
                ['🌸', 'pink', 'இளஞ்சிவப்பு'],
                ['🟣', 'purple', 'ஊதா'],
                ['🟤', 'brown', 'பிரவுன்'],
                ['⚫', 'black', 'கறுப்பு'],
                ['⚪', 'white', 'வெள்ளை'],
                ['🐘', 'grey', 'சாம்பல்'],
                ['🥇', 'gold', 'தங்க நிறம்'],
                ['🥈', 'silver', 'வெள்ளி நிறம்'],
                ['❓', 'What color?', 'என்ன நிறம்?', 'kind' => 'phrase'],
                ['👉', 'This color', 'இந்த நிறம்', 'kind' => 'phrase'],
                ['😍', 'I like this color', 'இந்த நிறம் பிடிக்கும்', 'kind' => 'phrase'],
                ['🙅', "I don't like this color", 'இந்த நிறம் பிடிக்காது', 'kind' => 'phrase'],
                ['🤲', 'I want this color', 'இந்த நிறம் வேணும்', 'kind' => 'phrase'],
                ['🔄', 'Different color', 'வேற நிறம்', 'kind' => 'phrase'],
                ['🟰', 'Same color', 'ஒரே நிறம்', 'kind' => 'phrase'],
            ]],
        ];
    }
}
