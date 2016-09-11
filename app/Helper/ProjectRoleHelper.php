<?php

namespace Kanboard\Helper;

use Kanboard\Core\Base;
use Kanboard\Core\Security\Role;

/**
 * Class ProjectRoleHelper
 *
 * @package Kanboard\Helper
 * @author  Frederic Guillot
 */
class ProjectRoleHelper extends Base
{
    /**
     * Get project role for the current user
     *
     * @access public
     * @param  integer $project_id
     * @return string
     */
    public function getProjectUserRole($project_id)
    {
        return $this->memoryCache->proxy($this->projectUserRoleModel, 'getUserRole', $project_id, $this->userSession->getId());
    }

    /**
     * Return true if the task can be moved by the connected user
     *
     * @param array $task
     * @return bool
     */
    public function isDraggable(array $task)
    {
        if ($task['is_active'] == 1 && $this->helper->user->hasProjectAccess('BoardViewController', 'save', $task['project_id'])) {
            $role = $this->getProjectUserRole($task['project_id']);

            if ($this->role->isCustomProjectRole($role)) {
                $srcColumnIds = $this->columnMoveRestrictionCacheDecorator->getAllSrcColumns($task['project_id'], $role);
                return isset($srcColumnIds[$task['column_id']]);
            }

            return true;
        }

        return false;
    }

    /**
     * Check if the user can move a task
     *
     * @param  int $project_id
     * @param  int $src_column_id
     * @param  int $dst_column_id
     * @return bool|int
     */
    public function canMoveTask($project_id, $src_column_id, $dst_column_id)
    {
        $role = $this->getProjectUserRole($project_id);

        if ($this->role->isCustomProjectRole($role)) {
            return $this->columnMoveRestrictionModel->isAllowed(
                $project_id,
                $role,
                $src_column_id,
                $dst_column_id
            );
        }

        return true;
    }

    /**
     * Return true if the user can remove a task
     *
     * Regular users can't remove tasks from other people
     *
     * @public
     * @param  array $task
     * @return bool
     */
    public function canRemoveTask(array $task)
    {
        if (isset($task['creator_id']) && $task['creator_id'] == $this->userSession->getId()) {
            return true;
        }

        if ($this->userSession->isAdmin() || $this->getProjectUserRole($task['project_id']) === Role::PROJECT_MANAGER) {
            return true;
        }

        return false;
    }

    /**
     * Check project access
     *
     * @param  string  $controller
     * @param  string  $action
     * @param  integer $project_id
     * @return bool
     */
    public function checkProjectAccess($controller, $action, $project_id)
    {
        if (! $this->userSession->isLogged()) {
            return false;
        }

        if ($this->userSession->isAdmin()) {
            return true;
        }

        if (! $this->helper->user->hasAccess($controller, $action)) {
            return false;
        }

        $role = $this->getProjectUserRole($project_id);

        if ($this->role->isCustomProjectRole($role)) {
            $restrictions = $this->projectRoleRestrictionModel->getAllByRole($project_id, $role);
            $result = $this->projectRoleRestrictionModel->isAllowed($restrictions, $controller, $action);
            $result = $result && $this->projectAuthorization->isAllowed($controller, $action, Role::PROJECT_MEMBER);
        } else {
            $result = $this->projectAuthorization->isAllowed($controller, $action, $role);
        }

        return $result;
    }
}
