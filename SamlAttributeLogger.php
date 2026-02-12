<?php
namespace SamlAttributeLogger;

use ExternalModules\AbstractExternalModule;

class SamlAttributeLogger extends AbstractExternalModule
{
    private $table = "saml_attributes";

    /**
     * Auto-create table ONLY if it does not exist.
     * No migrations. No schema changes.
     */
    public function redcap_module_system_enable()
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS {$this->table} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                firstname varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ShibuscDisplayGivenName',
                lastname varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ShibuscDisplaySn',
                email varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Shibmail',
                pvid varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ShibuscOwnerPvid',
                netid varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ShibuscNetID',
                uscid varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ShibuscUSCID',
                role varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ShibuscPrimaryAffiliation',
                client_ip varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Client IP address',
                timestamp datetime DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation time',
                KEY idx_email (email),
                KEY idx_uscid (uscid),
                KEY idx_timestamp (timestamp)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";

        $this->query($sql);
    }

    /**
     * Log SAML attributes once per session.
     * Schema strictly matches original working table.
     */
    public function redcap_every_page_top($project_id)
    {
        // Log only once per session
        if (isset($_SESSION['saml_attribute_logged'])) {
            return;
        }

        // Ensure SAML headers exist
        if (!isset($_SERVER['ShibuscDisplayGivenName'])) {
            return;
        }

        global $conn;

        $sql = "INSERT INTO {$this->table}
            (firstname, lastname, email, pvid, netid, uscid, role, client_ip)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("SAML Logger prepare failed: " . $conn->error);
            return;
        }

        $firstname = $_SERVER['ShibuscDisplayGivenName'] ?? null;
        $lastname  = $_SERVER['ShibuscDisplaySn'] ?? null;
        $email     = $_SERVER['Shibmail'] ?? null;
        $pvid      = $_SERVER['ShibuscOwnerPvid'] ?? null;
        $netid     = $_SERVER['ShibuscNetID'] ?? null;
        $uscid     = $_SERVER['ShibuscUSCID'] ?? null;
        $role      = $_SERVER['ShibuscPrimaryAffiliation'] ?? null;
        $client_ip = $_SERVER['REMOTE_ADDR'] ?? null;

        $stmt->bind_param(
            "ssssssss",
            $firstname,
            $lastname,
            $email,
            $pvid,
            $netid,
            $uscid,
            $role,
            $client_ip
        );

        if (!$stmt->execute()) {
            error_log("SAML Logger insert failed: " . $stmt->error);
        }

        $stmt->close();

        $_SESSION['saml_attribute_logged'] = true;
    }
}
?>
