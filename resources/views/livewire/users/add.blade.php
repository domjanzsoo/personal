<x-form-section submit="addUser">
    <x-slot name="title">
        {{ __('users.add') }}
    </x-slot>
    <x-slot name="description">
        {{ __('users.add_full') }}
    </x-slot>

    <x-slot name="form">
        <div class="grid grid-cols-4">
            <div class="mt-3">
                <x-profile-img :imgUrl="$state['profile_picture'] ? $state['profile_picture']->temporaryUrl() : null" size="16"/>
            </div>
            <div class="col-start-2 col-span-3">
                <x-label for="full_name" value="{{ __('users.full_name') }}" />
                <x-input id="full_name" type="text" class="mt-1 w-full" wire:model="state.full_name"/>
                <x-input-error for="state.full_name" class="mt-2" />
            </div>
        </div>
        <div>
            <x-label for="email" value="{{ __('users.email') }}" />
            <x-input id="email" type="text" class="mt-1 w-72" wire:model="state.email" />
            <x-input-error for="state.email" class="mt-2" />
        </div>
        <div>
            <x-label for="password" value="{{ __('users.password') }}" />
            <x-input id="password" type="password" class="mt-1 w-full" wire:model.live="state.password" />
            <x-input-error for="state.password" class="mt-2 form-control" />
        </div>
        <div>
            <x-label for="confirm_password" value="{{ __('users.confirm_password') }}"/>
            <x-input id="confirm_password" type="password" class="mt-1 w-72" wire:model.live="state.password_confirmation" />
            <x-input-error for="state.password_confirmation" class="mt-2" />
        </div>
        <div class="col-span-2 mt-3">
            <x-label for="profile_picture" value="{{ __('users.profile_picture') }}"/>
            <x-drag-and-drop-upload wire:model="state.profile_picture" class="w-full" fileType='profile-picture' />
            <x-input-error for="state.profile_picture" class="mt-2" />
        </div>
        <div class="flex flex-row justify-end col-span-2 pr-5 mt-6">
            <x-button x-data x-on:click="addUser" class="bg-blue ml-2">
                {{ __('general.submit') }}
            </x-button>
        </div>
    </x-slot>
</x-form-section>
