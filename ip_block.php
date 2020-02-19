<?php

/**
 * Plugin Name: Block IP Address
 * Version:     1.0.0
 * Author:      Ido Friedlander
 * Author URI:  https://github.com/idofri
 */

class BlockIpAddress
{
    private $pool = [
        '7.7.7.7',
        '8.8.8.8',
    ];

    private $ipAddress;

    private $visitTransient = '';

    private $blockTransient = '';

    public static function instance()
    {
        static $instance;
        return $instance ?? ($instance = new static);
    }

    protected function __construct()
    {
        $this->setIpAddress()
            ->setVisitTransient()
            ->setBlockTransient();

        add_action('wp', [$this, 'init']);
    }

    public function setIpAddress()
    {
        $this->ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
        return $this;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    public function setVisitTransient()
    {
        $this->visitTransient = 'visit_' . $this->getIpAddress();
        return $this;
    }

    public function getVisitTransient(): string
    {
        return $this->visitTransient;
    }

    public function setBlockTransient()
    {
        $this->blockTransient = 'block_' . $this->getIpAddress();
        return $this;
    }

    public function getBlockTransient(): string
    {
        return $this->blockTransient;
    }

    public function shouldBlockThisPage(): bool
    {
        return (strpos($_SERVER['REQUEST_URI'] ?? '', 'thankyou') !== false);
    }

    public function shouldBlockThisIp(): bool
    {
        return (bool) get_transient($this->getBlockTransient());
    }

    public function ipAddressExcluded(): bool
    {
        return in_array($this->getIpAddress(), $this->pool);
    }

    public function init()
    {
        if (!$this->shouldBlockThisPage() || $this->ipAddressExcluded()) {
            return;
        }

        if ($this->shouldBlockThisIp()) {
            return $this->blockIpAddress();
        }

        $visits = $this->getVisits();
        if ($visits >= 1) {
            set_transient($this->getBlockTransient(), true, $this->getBlockExpiration());
        }

        set_transient($this->getVisitTransient(), ++$visits, $this->getVisitRateExpiration());
    }

    public function blockIpAddress(): void
    {
        global $wp_query;
        status_header(401);
        $wp_query->set_404();
    }

    public function getBlockExpiration()
    {
        return DAY_IN_SECONDS;
    }

    public function getVisitRateExpiration()
    {
        return MINUTE_IN_SECONDS * 30;
    }

    public function getVisits(): int
    {
        $visits = get_transient($this->getVisitTransient());
        return $visits ? (int) $visits : 0;
    }
}

BlockIpAddress::instance();
