<?php

namespace App\Livewire\Roles;

use Livewire\Component;
use App\Contract\RoleRepositoryInterface;
use App\Contract\PermissionRepositoryInterface;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Attributes\Renderless;

class Edit extends Component
{
    private $roleRepository;
    private $permissionRepository;
    
    const ENTITY = 'role';

    protected $listeners = [
        'role-permissions'       => 'handlePermissions',
        'open-edit-modal'        => 'handleEditModalData',
        'modal-closed'           => 'resetFields',
        'save-modal-edit-role'   => 'save'
    ];
    
    public array $state = [
        'role_name'             => null,
        'permissions'           => [],
        'selected_permissions'  => [],
        'permission_update'     => [],
        'id'                    => null
    ];

    protected function rules(): array
    {
        return [
            'state.role_name'           => 'required|unique:roles,name,' . $this->state['id'],
            'state.permission_update'   => 'array',
            'state.id'                  => 'int'
        ];
    }

    public function messages(): array
    {
        return [
            'state.role_name.required' => trans('validation.required', ['attribute' => 'name']),
            'state.role_name.unique' => trans('validation.unique', ['attribute' => 'role name'])
        ];
    }

    public function render()
    {
        return view('livewire.roles.edit', [
            'permissions' => $this->permissionRepository->getAll('name')
        ]);
    }

    public function boot(
        RoleRepositoryInterface $roleRepository,
        PermissionRepositoryInterface $permissionRepository
    )
    {
        $this->roleRepository = $roleRepository;
        $this->permissionRepository = $permissionRepository;
    }

    public function handleEditModalData($itemId, $entity): void
    {
        if ($entity === self::ENTITY) {
            $role = $this->roleRepository->getById($itemId);

            $this->state['role_name'] = $role->name;
            $this->state['selected_permissions'] = $role->permissions;
            $this->state['permission_update'] = $role->permissions()->pluck('permissions.id')->toArray();
            $this->state['permissions'] = $role->permissions->pluck('permissions.id')->toArray();
            $this->state['id'] = $itemId;
        }

        return;
    }

    public function resetFields()
    {
        $this->state['role_name'] = null;
        $this->state['selected_permissions'] = [];
        $this->state['id'] = null;

        $this->dispatch(self::ENTITY . '-permissions-cleared', ['entity' => self::ENTITY]);
    }

    #[Renderless]
    public function handlePermissions(array $selections): void
    {
        $this->state['permission_update'] = [];

        foreach ($selections as $id => $selection) {
            if ($selection['selected'] && !in_array($id, $this->state['permissions'])) {
                array_push($this->state['permission_update'], $id);
            }
        }
    }

    public function save(): void
    {
        if (!access_control()->canAccess(auth()->user(), 'edit_role')) {
            throw new AuthorizationException(trans('errors.unauthorized_action', ['action' => 'edit role']));
        }

        $validatedData = $this->validate();

        try {
            $role = $this->roleRepository->getById($validatedData['state']['id']);

            $this->roleRepository->update($role, ['name' => $validatedData['state']['role_name']]);

            $this->roleRepository->updatePermissions($role, $validatedData['state']['permission_update']);

            $this->resetFields();

            $this->dispatch(self::ENTITY . '-edited', ['entity' => self::ENTITY]);
            $this->dispatch('toastr', ['type' => 'confirm', 'message' => trans('notifications.successfull_update', ['entity' => 'Role'])]);
        } catch (Exception $exception) {
            $this->dispatch('toastr', ['type' => 'error', 'message' => $exception->getMessage()]);
        }
     
        return;
    }
}
