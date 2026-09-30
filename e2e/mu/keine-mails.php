<?php
// Nur für den E2E-Test: WooCommerce-Mails gelingen, ohne dass ein Mailserver läuft.
add_filter('pre_wp_mail', '__return_true');
