<x-app-layout>
    <x-slot name="title">Profile</x-slot>

    <x-pesu.page-header :title="__('Profile')" subtitle="Your name, email and password." />

    <x-pesu.card>
        @include('profile.partials.update-profile-information-form')
    </x-pesu.card>

    <x-pesu.card>
        @include('profile.partials.update-password-form')
    </x-pesu.card>

    <x-pesu.card>
        @include('profile.partials.delete-user-form')
    </x-pesu.card>
</x-app-layout>
