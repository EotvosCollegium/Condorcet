<?php
/*
    Condorcet PHP Class, with Schulze Methods and others !

    By Julien Boudry - MIT LICENSE (Please read LICENSE.txt)
    https://github.com/julien-boudry/Condorcet
*/
declare(strict_types=1);

namespace Condorcet\Timer;

use Condorcet\Timer\Manager;
use Condorcet\CondorcetVersion;

class Chrono
{
    use CondorcetVersion;

    protected $_manager;
    protected $_start;
    protected $_role;

    public function __construct (Manager $timer, $role = null)
    {
        $this->_manager = $timer;
        $this->setRole($role);        
        $this->resetStart();
        $this->managerStartDeclare();
    }

    public function __destruct () {
        $this->_manager->addTime($this);
    }

    public function getStart () : float {
        return $this->_start;
    }

    public function getTimerManager () : Manager {
        return $this->_manager;
    }

    protected function resetStart () : void {
        $this->_start = microtime(true);
    }

    public function getRole () : ?string {
        return $this->_role;
    }

    public function setRole ($role) : void {
        $this->_role = ($role !== null) ? (string) $role : $role;
    }

    protected function managerStartDeclare () : void {
        $this->_manager->startDeclare( $this );
    }
}
