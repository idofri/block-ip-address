<?php

/**
 * Plugin Name: Block IP Address (for cf7)
 * Version:     1.0.0
 * Author:      Ido Friedlander
 * Author URI:  https://github.com/idofri
 */

class BlockIpAddress
{
    public static function instance()
    {
        static $instance;
        return $instance ?? ($instance = new static);
    }

    protected function __construct()
    {
        add_action('wpcf7_before_send_mail', [$this, 'cf7AfterSubmission'], 10, 3);
        add_filter('wpcf7_submission_is_blacklisted', [$this, 'cf7BeforeSubmission'], 10, 2);
    }

    public function cf7BeforeSubmission($blacklisted, $submission)
    {
        $ipAddress = $submission->get_meta('remote_ip');
        if (!$this->ipAddressExcluded($ipAddress, $submission->get_contact_form())) {
            return $this->shouldBlockThisIp($ipAddress);
        }
        return $blacklisted;
    }

    public function cf7AfterSubmission(WPCF7_ContactForm $form, $abort, WPCF7_Submission $submission)
    {
        $ipAddress = $submission->get_meta('remote_ip');
        $submits = $this->getSubmits($ipAddress);
        if ($submits >= 1) {
            set_transient($this->getBlockTransient($ipAddress), true, $this->getBlockExpiration());
        }
        set_transient($this->getSubmitTransient($ipAddress), ++$submits, $this->getSubmitRateExpiration());
    }

    public function getSubmitTransient(string $ipAddress): string
    {
        return 'submit_' . $ipAddress;
    }

    public function getBlockTransient(string $ipAddress): string
    {
        return 'block_' . $ipAddress;
    }

    public function shouldBlockThisIp(string $ipAddress): bool
    {
        return (bool) get_transient($this->getBlockTransient($ipAddress));
    }

    public function ipAddressExcluded(string $ipAddress, WPCF7_ContactForm $form): bool
    {
        $excludedIps = array_map('trim', explode(',', $form->additional_setting('excluded_ips')[0] ?? ''));
        return in_array($ipAddress, $excludedIps);
    }

    public function getBlockExpiration(): int
    {
        return DAY_IN_SECONDS;
    }

    public function getSubmitRateExpiration(): int
    {
        return MINUTE_IN_SECONDS * 30;
    }

    public function getSubmits(string $ipAddress): int
    {
        $submits = get_transient($this->getSubmitTransient($ipAddress));
        return $submits ? (int) $submits : 0;
    }
}

BlockIpAddress::instance();
