<?php

namespace Sharpie89\MultiFormRequest\Http\Requests;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use ReflectionException;
use ReflectionMethod;

abstract class MultiFormRequest extends FormRequest
{
    /**
     * @var Collection
     */
    private $multiFormRequests;

    /**
     * @throws ReflectionException
     * @throws ValidationException
     * @throws AuthorizationException
     * @throws BindingResolutionException
     */
    public function validateResolved(): void
    {
        $this->setMultiFormRequests();
        $this->prepareForValidation();

        if (!$this->passesAuthorization()) {
            $this->failedAuthorization();
        }

        if ($this->isFirstMultiFormRequest()) {
            $this->validateMultiFormRequests();
        }

        $this->replaceDataByRules();

        $instance = $this->getValidatorInstance();

        if ($instance->passes()) {
            $this->passedValidation();
        }
    }

    /**
     * @throws BindingResolutionException
     * @throws ValidationException
     */
    private function validateMultiFormRequests(): void
    {
        $data = $rules = $messages = $attributes = [];

        $validators = $this->getMultiFormRequests()->map(function (string $class) {
            /** @var self $multiFormRequest */
            if ($class === static::class) {
                $multiFormRequest = $this;
            } else {
                $multiFormRequest = new $class;
                $multiFormRequest->setContainer($this->container);
                $multiFormRequest->setRedirector(app(Redirector::class));
                $multiFormRequest->initialize(
                    $this->query->all(),
                    $this->request->all(),
                    $this->attributes->all(),
                    $this->cookies->all(),
                    $this->files->all(),
                    $this->server->all(),
                    $this->getContent()
                );

                $multiFormRequest->prepareForValidation();
            }

            return $multiFormRequest->getValidatorInstance();
        })->each(static function (Validator $validator) use (&$data, &$rules, &$messages, &$attributes) {
            $data = array_merge($data, $validator->getData());
            $rules = array_merge($rules, $validator->getRules());
            $messages = array_merge($messages, $validator->customMessages);
            $attributes = array_merge($attributes, $validator->customAttributes);
        });

        $validator = $this->container
            ->make(ValidationFactory::class)
            ->make($data, $rules, $messages, $attributes);

        if ($validator->fails()) {
            $this->failedValidation($validator);
        }
    }

    protected function replaceDataByRules(): void
    {
        $this->replace(array_intersect_key(
            $this->request->all(),
            $this->container->call([$this, 'rules'])
        ));
    }

    /**
     * @throws ReflectionException
     */
    private function setMultiFormRequests(): void
    {
        $this->multiFormRequests = new Collection;

        $controllerAction = Route::getCurrentRoute()
            ->getActionName();

        $controllerMethod = explode('@', $controllerAction);
        $reflectionMethod = new ReflectionMethod(...$controllerMethod);

        foreach ($reflectionMethod->getParameters() as $parameter) {
            $class = $parameter->getClass()->getName();

            if (is_subclass_of($class, self::class)) {
                $this->multiFormRequests->push($class);
            }
        }
    }

    private function isFirstMultiFormRequest(): bool
    {
        return $this->getMultiFormRequests()->first() === static::class;
    }

    protected function getMultiFormRequests(): Collection
    {
        return $this->multiFormRequests;
    }
}