<?php
/**
 * UserFrosting (http://www.userfrosting.com)
 *
 * @link      https://github.com/userfrosting/UserFrosting
 * @copyright Copyright (c) 2013-2016 Alexander Weissman
 * @license   https://github.com/userfrosting/UserFrosting/blob/master/licenses/UserFrosting.md (MIT License)
 */
namespace UserFrosting\Sprinkle\Account\Model;

use Illuminate\Database\Capsule\Manager as Capsule;
use UserFrosting\Sprinkle\Core\Model\UFModel;
use UserFrosting\Sprinkle\Core\Model\Relations\BelongsToManyThrough;

/**
 * Permission Class.
 *
 * Represents a permission for a role or user.
 * @author Alex Weissman (https://alexanderweissman.com)
 * @see http://www.userfrosting.com/components/#authorization
 * @property string slug
 * @property string name
 * @property string conditions
 * @property string description
 */
class Permission extends UFModel
{
    /**
     * @var string The name of the table for the current model.
     */
    protected $table = "permissions";

    protected $fillable = [
        "slug",
        "name",
        "conditions",
        "description"
    ];

    /**
     * @var bool Enable timestamps for this class.
     */
    public $timestamps = true;

    /**
     * Delete this permission from the database, removing associations with roles.
     *
     */
    public function delete()
    {
        // Remove all role associations
        $this->roles()->detach();

        // Delete the permission
        $result = parent::delete();

        return $result;
    }

    /**
     * Get a list of roles to which this permission is assigned.
     */
    public function roles()
    {
        /** @var UserFrosting\Sprinkle\Core\Util\ClassMapper $classMapper */
        $classMapper = static::$ci->classMapper;

        return $this->belongsToMany($classMapper->getClassMapping('role'), 'permission_roles', 'permission_id', 'role_id')->withTimestamps();
    }

    /**
     * Query scope to get all permissions assigned to a specific role.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $roleId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForRole($query, $roleId)
    {
        return $query->join('permission_roles', function ($join) use ($roleId) {
            $join->on('permission_roles.permission_id', 'permissions.id')
                 ->where('role_id', $roleId);
        });
    }

    /**
     * Query scope to get all permissions NOT associated with a specific role.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $roleId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNotForRole($query, $roleId)
    {
        return $query->join('permission_roles', function ($join) use ($roleId) {
            $join->on('permission_roles.permission_id', 'permissions.id')
                 ->where('role_id', '!=', $roleId);
        });
    }

    /**
     * Get a list of users who have this permission, along with a list of roles through which each user has the permission.
     */
    public function users()
    {
        /** @var UserFrosting\Sprinkle\Core\Util\ClassMapper $classMapper */
        $classMapper = static::$ci->classMapper;

        // This relationship maps the permission to its roles.
        $intermediateRelationship = $this->belongsToMany($classMapper->getClassMapping('role'), 'permission_roles', 'permission_id', 'role_id', 'roles')
            ->withPivot('permission_id');

        // Next, we need to find the users who have each of these roles.
        
        // If no relationship name was passed, we will pull backtraces to get the
        // name of the calling function. We will use that function name as the
        // title of this relation since that is a great convention to apply.
        $relation = $this->guessBelongsToManyRelation();

        // Now pass this query in to create a new `BelongsToManyThrough` relationship on this parent permission
        $instance = $this->newRelatedInstance($classMapper->getClassMapping('user'));

        $query = new BelongsToManyThrough(
            $instance->newQuery(), $this, $intermediateRelationship, 'role_users', 'role_id', 'user_id', $relation
        );

        return $query;
    }
}
