<?php

class PermissionHandler
{
    private $permissions;
    private $mediumPermissionId;
    private $highPermissionId;

    /**
     * Constructor para inicializar la clase con los permisos y los niveles.
     *
     * @param array $permissions Array of permissions filtered for the logged-in user.
     * @param int|null $mediumPermissionId Permission ID for medium level (optional).
     * @param int|null $highPermissionId Permission ID for high level (optional).
     */
    public function __construct(array $permissions, ?int $mediumPermissionId = null, ?int $highPermissionId = null)
    {
        $this->permissions = array_column($permissions, 'id_restringido'); // Extract permission IDs
        $this->mediumPermissionId = $mediumPermissionId;
        $this->highPermissionId = $highPermissionId;
    }

    /**
     * Determine the access level of the user.
     *
     * @return string Returns the access level: 'low', 'medium', or 'high'.
     */
    public function getAccessLevel(): string
    {
        if ($this->isHigh()) {
            return 'high';
        }

        if ($this->isMedium()) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Check if the access level is low.
     *
     * @return bool True if access level is low, false otherwise.
     */
    public function isLow(): bool
    {
        return empty($this->permissions) ||
            ($this->mediumPermissionId === null && $this->highPermissionId === null) ||
            (!in_array($this->mediumPermissionId, $this->permissions) && !in_array($this->highPermissionId, $this->permissions));
    }

    /**
     * Check if the access level is medium.
     *
     * @return bool True if access level is medium, false otherwise.
     */
    public function isMedium(): bool
    {
        return $this->mediumPermissionId !== null
            && in_array($this->mediumPermissionId, $this->permissions)
            && !$this->isHigh();
    }

    /**
     * Check if the access level is high.
     *
     * @return bool True if access level is high, false otherwise.
     */
    public function isHigh(): bool
    {
        return $this->highPermissionId !== null && in_array($this->highPermissionId, $this->permissions);
    }
}
