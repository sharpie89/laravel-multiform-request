# Laravel multi form request

If you don't like the idea of cluttering your controller to extract parameters from a single form request when having 2 or more entities or models, this may suit you. The `$request->validated()` method only returns the parameters that belong to that `MultiFormRequest` class and the thrown `ValidationException` returns a `MessageBag` that contains all fields.

## Installation

`composer require sharpie89/laravel-multiform-request`

## Usage

Just extend your current `FormRequest` with `MultiFormRequest` and chain the requests together in your controller method.

## Examples

Use `Sharpie89\MultiFormRequest\Http\Requests\MultiFormRequest` instead of `Illuminate\Foundation\Http\FormRequest`:

```php
use Sharpie89\MultiFormRequest\Http\Requests\MultiFormRequest;

class StoreBookRequest extends MultiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
            ],
        ];
    }
}
```


```php
use Sharpie89\MultiFormRequest\Http\Requests\MultiFormRequest;

class StoreBookDetailRequest extends MultiFormRequest
{
    public function rules(): array
    {
        return [
            'comments' => [
                'string',
            ],
            'reviews' => [
                'string',
            ],
        ];
    }
}
```

Chain the requests inside the controller method:

```php
class BookController extends Controller
{
    public function store(
        StoreBookRequest $storeBookRequest,
        StoreBookDetailRequest $storeBookDetailRequest
    ): RedirectResponse {
        $book = new Book($storeBookRequest->validated());
        $book->save();
        
        $bookDetail = new BookDetail($storeBookDetailRequest->validated());
        $book->detail()->save($bookDetail);
        
        return redirect()
            ->route('books.show', $book->getKey())
            ->with('message', 'Book and detail have been updated!');
    }
}
```

## Notes

- `$request->all()` and `$request->validated()` only return the validation data that is exclusive to that request (by intersecting the rules array).


