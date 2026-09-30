<?php
// Entfernt die Einstellung des Plugins beim Löschen über das WordPress-Backend.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}
delete_option('rappenrundung_bezeichnung');
