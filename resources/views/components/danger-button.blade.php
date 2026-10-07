<button {{ $attributes->merge(['type' => 'submit', 'class' => 'pesu-btn pesu-btn-danger']) }}>
    {{ $slot }}
</button>
