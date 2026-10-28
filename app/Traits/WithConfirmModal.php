<?php

namespace App\Traits;

trait WithConfirmModal
{
    public bool   $showConfirm    = false;
    public string $confirmMethod  = '';
    public mixed  $confirmId      = null;
    public string $confirmTitle   = '';
    public string $confirmMessage = '';
    public string $confirmVariant = 'primary'; // primary | warning | danger

    public function askConfirm(string $method, mixed $id = null, string $title = 'Confirmation', string $message = '', string $variant = 'primary'): void
    {
        $this->confirmMethod  = $method;
        $this->confirmId      = $id;
        $this->confirmTitle   = $title;
        $this->confirmMessage = $message;
        $this->confirmVariant = $variant;
        $this->showConfirm    = true;
    }

    public function cancelConfirm(): void
    {
        $this->showConfirm   = false;
        $this->confirmMethod = '';
        $this->confirmId     = null;
    }

    public function executeConfirmed(): void
    {
        $method = $this->confirmMethod;
        $id     = $this->confirmId;
        $this->cancelConfirm();
        if ($method && method_exists($this, $method)) {
            $id !== null ? $this->$method($id) : $this->$method();
        }
    }
}
