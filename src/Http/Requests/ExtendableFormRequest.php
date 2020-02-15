<?php

namespace Sharpie89\MultiFormRequest\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Redirector;

abstract class ExtendableFormRequest extends FormRequest
{
    /**
     * @var array
     */
    protected $initialData = [];

    protected function extend(string $class): self
    {
        /** @var self $formRequest */
        $formRequest = new $class;
        $formRequest->setContainer(app());
        $formRequest->setRedirector(app(Redirector::class));
        $formRequest->initialize(
            $this->query->all(),
            $this->getInitialData(),
            $this->attributes->all(),
            $this->cookies->all(),
            $this->files->all(),
            $this->server->all(),
            $this->getContent()
        );

        return $formRequest;
    }

    protected function setInitialData(): void
    {
        $this->initialData = $this->request->all();
    }

    public function getInitialData(): array
    {
        return $this->initialData;
    }

    public function replaceDataByRules(): void
    {
        $this->replace(array_intersect_key($this->all(), $this->rules()));
    }
}
