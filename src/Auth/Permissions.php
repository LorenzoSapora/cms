<?php

namespace Statamic\Auth;

class Permissions
{
    protected $permissions = [];
    protected $pendingGroup = null;

    public function make(string $value)
    {
        $permission = (new Permission)->value($value);

        if ($this->pendingGroup) {
            $permission->inGroup($this->pendingGroup);
        }

        return $permission;
    }

    public function register($permission, $callback = null)
    {
        if (! $permission instanceof Permission) {
            $permission = self::make($permission);

            if ($callback) {
                $callback($permission);
            }
        }

        $this->permissions[] = $permission;

        return $permission;
    }

    public function all()
    {
        return collect($this->permissions)->flatMap(function ($permission) {
            return $this->mergePermissions($permission);
        })->keyBy->value();
    }

    protected function mergePermissions($permission)
    {
        return $permission->permissions()
            ->merge($permission->children()->flatMap(function ($perm) {
                return $this->mergePermissions($perm);
            }));
    }

    public function tree()
    {
        $tree = collect($this->permissions)
            ->flatMap(function ($permission) {
                return $permission->permissions()->flatMap->toTree();
            })
            ->groupBy(function ($permission) {
                return $permission['group'] ?? 'misc';
            });

            // Place ungrouped permissions at the end.
            if ($tree->has('misc')) {
                $tree->put('misc', $tree->pull('misc'));
            }

            $tree = $tree->map(function ($permissions, $group) {
                return [
                    'handle' => $group,
                    'permissions' => $permissions->all(),
                ];
            });

        return $tree->values();
    }

    public function group($name, $permissions)
    {
        throw_if($this->pendingGroup, new \Exception('Cannot double nest permission groups'));

        $this->pendingGroup = $name;

        $permissions();

        $this->pendingGroup = null;
    }
}
