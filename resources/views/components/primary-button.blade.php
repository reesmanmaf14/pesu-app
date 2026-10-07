<button {{ $attributes->merge(['type' => 'submit', 'class' => 'pesu-btn pesu-btn-primary']) }}>
    {{ $slot }}
</button>
