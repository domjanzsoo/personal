<?php

namespace App\Livewire\Users;

use Livewire\Component;
use App\Contract\UserRepositoryInterface;
use App\Contract\PermissionRepositoryInterface;
use App\Contract\RoleRepositoryInterface;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\WithFileUploads;
use DateTime;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Renderless;

class Edit extends Component
{
    use WithFileUploads;

    private $userRepository;
    private $permissionRepository;
    private $roleRepository;

    public $user;

    const ENTITY = 'user';

    public array $state = [
        'id'                    => null,
        'full_name'             => null,
        'email'                 => null,
        'password'              => null,
        'password_confirmation' => null,
        'verified'              => false,
        'profile_picture'       => null,
        'permission_update'     => [],
        'selected_permissions'  => [],
        'role_update'           => [],
        'selected_roles'        => []
    ];

    protected function rules(): array
    {
        return [
            'state.full_name'               => 'required',
            'state.email'                   => 'required|email|unique:users,email,' . $this->state['id'],
            'state.password'                => ['confirmed', 'nullable', Password::min(6)->mixedCase()->symbols()],
            'state.password_confirmation'   => 'nullable',
            'state.profile_picture'         => 'image|max:2048|nullable',
            'state.permission_update'       => 'array|nullable',
            'state.role_update'             => 'array|nullable',
        ];
    }

    public function messages(): array
    {
        return [
            'state.full_name.required' => trans('validation.required', ['attribute' => 'name']),
            'state.email.required' => trans('validation.required', ['attribute' => 'email']),
            'state.email.unique' => trans('validation.unique', ['attribute' => 'user email']),
            'state.email.email' => trans('validation.email', ['attribute' => 'user email']),
            'state.password.min' => trans('validation.min.string', ['attribute' => 'password', 'min' => 6]),
            'state.password.confirmed' => trans('validation.confirmed', ['attribute' => 'password']),
            'state.profile_picture.image' => trans('validation.image', ['attribute' => 'profile picture']),
            'state.profile_picture.max' => trans('validation.max.file', ['max' => '2048', 'attribute' => 'profile picture'])
        ];
    }

    protected $listeners = [
        'open-edit-modal'       => 'handleEditModalData',
        'user-permissions'      => 'handlePermissions',
        'user-roles'            => 'handleRoles',
        'modal-closed'          => 'resetFields',
        'save-modal-edit-user'  => 'save'
    ];

    public function render()
    {
        return view('livewire.users.edit', [
            'permissions' => $this->permissionRepository->getAll(),
            'roles' => $this->roleRepository->getAll()
        ]);
    }

    public function updatedStatePassword(): void
    {
        $this->validateOnly('state.password');
    }

    public function updatedStatePasswordConfirmation(): void
    {
        $this->validateOnly('state.password');
    }

    public function boot(
        UserRepositoryInterface $userRepository,
        PermissionRepositoryInterface $permissionRepository,
        RoleRepositoryInterface $roleRepository
    )
    {
        $this->userRepository = $userRepository;
        $this->permissionRepository = $permissionRepository;
        $this->roleRepository = $roleRepository;
    }

    public function handleEditModalData($itemId, $entity): void
    {
        if ($entity === self::ENTITY) {
            $this->user = $this->userRepository->getById($itemId);

            $this->state['full_name'] = $this->user->name;
            $this->state['email'] = $this->user->email;
            $this->state['selected_permissions'] = $this->user->permissions;
            $this->state['permission_update'] = $this->user->permissions()->pluck('permissions.id')->toArray();
            $this->state['selected_roles'] = $this->user->roles;
            $this->state['role_update'] = $this->user->roles()->pluck('roles.id')->toArray();
            $this->state['profile_picture'] = null;
            $this->state['id'] = $itemId;
        }
    }

    public function resetFields()
    {
        $this->user = null;
        $this->state['full_name'] = null;
        $this->state['email'] = null;
        $this->state['selected_permissions'] = [];
        $this->state['profile_picture'] = null;
        $this->state['id'] = null;

        $this->dispatch(self::ENTITY . '-permissions-cleared', ['entity' => self::ENTITY]);
    }

    #[Renderless]
    public function handlePermissions(array $selections): void
    {
        $this->state['permission_update'] = [];

        foreach ($selections as $id => $selection) {
            if ($selection['selected']) {
                array_push($this->state['permission_update'], $id);
            }
        }
    }

    #[Renderless]
    public function handleRoles(array $selections): void
    {
        $this->state['role_update'] = [];

        foreach ($selections as $id => $selection) {
            if ($selection['selected']) {
                array_push($this->state['role_update'], $id);
            }
        }
    }

    public function save(): void
    {
        if (!access_control()->canAccess(auth()->user(), 'edit_user')) {
            throw new AuthorizationException(trans('errors.unauthorized_action', ['action' => 'edit user']));
        }

        $validatedData = $this->validate();

        try {
            $userUpdateData = [
                'name' => $validatedData['state']['full_name'],
                'email' => $validatedData['state']['email']
            ];

            if ($validatedData['state']['password']) {
                $userUpdateData['password'] = Hash::make($validatedData['state']['password']);
            }

            if ($validatedData['state']['profile_picture']) {
                $currentDateTime = new DateTime();

                $profilePictureFileName = md5($this->user->id) . '-' . $currentDateTime->format('Y-m-d-H-i-s') . '.' . $validatedData['state']['profile_picture']->extension();
    
                $validatedData['state']['profile_picture']->storeAs(explode('/', config('filesystems.user_profile_image_path'))[1], $profilePictureFileName, $disk = config('filesystems.default'));
    
                $userUpdateData['profile_photo_path'] = config('filesystems.user_profile_image_path') . '/' . $profilePictureFileName;
            }

            $this->userRepository->update(
                $this->user,
                $userUpdateData
            );

            $this->userRepository->updatePermissions($this->user, $validatedData['state']['permission_update']);

            $this->userRepository->updateRoles($this->user, $validatedData['state']['role_update']);

            $this->resetFields();

            $this->dispatch(self::ENTITY . '-edited', ['entity' => self::ENTITY]);
            $this->dispatch('toastr', ['type' => 'confirm', 'message' => trans('notifications.successfull_update', ['entity' => 'User'])]);
        } catch(Exception $exception) {
            $this->dispatch('toastr', ['type' => 'error', 'message' => $exception->getMessage()]);
        }

        return;
    }
}
