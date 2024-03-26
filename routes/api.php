<?php

use App\Models\File;
use App\Models\User;
use App\Rules\PassRule;
use GuzzleHttp\Psr7\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
// use Nette\Utils\Strings;
use PhpParser\Node\Expr\Cast\String_;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });



Route::addRoute('POST', '/authorization', function (Request $request) {
    $validator = Validator::make($request->all(), [
        'email' => 'required|email',
        'password' => 'required|min:3'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'messages' => $validator->messages()
        ], 422);
    }

    $data = $validator->validate();

    $login = $data['email'];
    $password = $data['password'];

    $user = User::where('email', $login)->where('password', $password)->first();
    if ($user) {
        $user->token = Str::random(64);
        $user->save();

        return response()->json([
            "success" => true,
            "message" => "Success",
            "token" => $user->token
        ], 200)->cookie('Authorization', 'Bearer ' . $user->token);
    } else {
        return response()->json([
            "success" => false,
            "message" => "Login failed"
        ], 401);
    }
})->middleware('cors');



Route::addRoute("POST", "/registration", function (Request $request) {
    // {
    //     "email": "admin@admin.ru",
    //     "password": "Qa1",
    //     "first_name": "name",
    //     "last_name": "last_name"
    //  }

    $validator = Validator::make($request->all(), [
        "email" => "required|email",
        "password" => ['required', new PassRule],
        "first_name" => "required",
        "last_name" => "required"
    ]);

    if ($validator->fails()) {
        return response()->json([
            "success" => false,
            "messages" => $validator->messages()
        ], 422);
    }

    $data = $validator->validate();

    $user = new User([
        "email" => $data['email'],
        "first_name" => $data['first_name'],
        "last_name" => $data["last_name"],

    ]);

    $user->token = Str::random(64);
    $user->password = $data['password'];

    $user->save();

    return response()->json([
        "message" => "Success",
        "success" => true,
        "token" => $user->token
    ], 200);
})->middleware('cors');

// Route::addRoute("GET", "/logout", function (Request $request) {

// });

Route::get('/logout', function (Request $request) {
    $user = $request->attributes->get('user');

    $user->token = null;
    $user->save();

    return response()->json([
        "success" => true,
        "message" => "Logout"
    ])->cookie('Authorization', null);
})->middleware(['myauth', 'cors']);

Route::middleware('myauth')->post('files', function (Request $request) {


    if (!$request->hasFile('files')) return response()->json([
        'success' => false
    ], 400);

    $files = [];
    $errors = [];

    // try {
    $validator = Validator::make($request->all(), [
        "files.*" => "required|file|mimes:txt,jpg,png|max:2048"
    ]);

    if ($validator->fails()) {
        foreach (array_keys($validator->failed()) as $f) {
            $errors[] = [
                'success' => false,
                'message' => 'File not loaded',
                'name' => $request->file($f)->getClientOriginalName()
            ];
        }
    }

    try {
        $validated = $validator->validated();
        $files = $validated['files'];
    } catch (ValidationException $e) {
        // $errors[] = [
        //     'success' => false,
        //     // 'message' => $e,
        // ];
    }

    $user = $request->attributes->get('user');

    $paths = [];
    // $files->storeAs('files', $files->getClientOriginalName)
    foreach ($files as $file) {
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $ext = $file->getClientOriginalExtension();

        $filename = $originalName . '.' . $ext;

        $i = 1;
        while (Storage::exists('files/' . $filename)) {
            $filename = $originalName . ' (' . $i . ').' . $ext;
            $i++;
        }

        $file->storeAs('files', $filename);

        $fileData = new File([
            'file_id' => Str::random(10),
            'url' => '/files/' . $filename,
            'owner_id' => $user->id
        ]);


        $user1 = User::find($user->id);
        $fileData->save();
        $fileData->owners()->save($user1, ['type' => 'author']);
        $fileData->save();

        $fileData['success'] = true;
        $fileData['message'] = 'Success';

        $paths[] = $fileData;
    }

    return array_merge($paths, $errors);
})->middleware('cors');

Route::addRoute('PATCH', '/files/{file_id}', function (Request $request, $file_id) {
    $validator = Validator::make($request->all(), [
        "name" => "required",
    ]);

    if ($validator->fails()) {
        return response()->json([
            "success" => false,
            "messages" => $validator->messages()
        ], 422);
    }

    $data = $validator->validate();

    $file = File::where('file_id', $file_id)->first();

    if (!$file) {
        return response()->json([
            // "success" => false,
            "message" => "Not found"
        ], 404);
    }

    if ($file->owner_id !== $request->attributes->get('user')->id) {
        return response()->json([
            // "success" => false,
            "message" => "Fobidden for you"
        ], 403);
    }

    $base = pathinfo($file->url, PATHINFO_DIRNAME);
    $newPath = $base . "/" . $data['name'];

    if (Storage::exists($newPath)) {
        return response()->json([
            "success" => "false",
            "message" => "имя не уникально"
        ], 422);
    }


    Storage::move(substr($file->url, 1), substr($newPath, 1));

    $file->url = $newPath;
    $file->save();

    return [
        'success' => true,
        'message' => "Renamed"
    ];
})->middleware(['myauth', 'cors']);

Route::addRoute('DELETE', '/files/{file_id}', function (Request $request, $file_id) {

    $file = File::where('file_id', $file_id)->first();

    if (!$file) {
        return response()->json([
            // "success" => false,
            "message" => "Not found"
        ], 404);
    }

    if ($file->owner_id !== $request->attributes->get('user')->id) {
        return response()->json([
            // "success" => false,
            "message" => "Fobidden for you"
        ], 403);
    }
    Storage::delete($file->url);
    $file->delete();

    return [
        'success' => true,
        'message' => "File already deleted"
    ];
})->middleware(['myauth', 'cors']);


Route::addRoute('GET', '/files/{file_id}', function (Request $request, $file_id) {

    $file = File::where('file_id', $file_id)->first();
    $user = $request->attributes->get('user');

    if (!$file) {
        return response()->json([
            // "success" => false,
            "message" => "Not found"
        ], 404);
    }

    $file->load('owners');

    $owner = null;
    foreach ($file->owners as $own) {
        if ($own->id === $user->id) $owner = $own;
    }

    if (!$owner) {
        return response()->json([
            // "success" => false,
            "message" => "Fobidden for you"
        ], 403);
    }

    return Storage::download(substr($file->url, 1));
})->middleware(['myauth', 'cors']);

Route::post('/files/{file_id}/accesses', function (Request $request, $file_id) {
    $validator = Validator::make($request->all(), [
        "email" => "required|email",
    ]);

    if ($validator->fails()) {
        return response()->json([
            "success" => false,
            "messages" => $validator->messages()
        ], 422);
    }

    $data = $validator->validate();

    $file = File::where('file_id', $file_id)->first();

    if (!$file) {
        return response()->json([
            // "success" => false,
            "message" => "Not found"
        ], 404);
    }

    if ($file->owner_id !== $request->attributes->get('user')->id) {
        return response()->json([
            // "success" => false,
            "message" => "Fobidden for you"
        ], 403);
    }

    $newOwner = User::where('email', $data['email'])->first();
    if (!$newOwner) {


        return response()->json([
            // "success" => false,
            "message" => "F U!"
        ], 404);
    }

    $file->owners()->attach($newOwner, ["type" => 'co-author']);
    
    $res = [];

    foreach ($file->owners as $owner) {
        $res[] = [
            "fullname" =>$owner->first_name.' '.$owner->last_name,
            "email" => $owner->email,
            "type" => $owner->pivot->type
        ];
    }

    return $res;

})->middleware('myauth');


// Route::addRoute('GET', '/info', function (Request $request) {
//     $name = $request->query('name');

//     return   'fuck u '.$name ;
// });

// Route::addRoute("POST", '/files',  function (Request $request) {

//     if ($request->hasFile('files')) {

//         $files = $request->file('files');
//         // Массив для хранения путей к файлам
//         $paths = [];

//         // Проход по каждому файлу

        
//         foreach ($files as $file) {
//             // Сохранение файла в директории 'uploads' и получение его имени

//             $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
//             $filename = $originalName;
//             $extension = $file->getClientOriginalExtension();

//             $index = 1;
//             while(Storage::exists('uploads/'.$filename.'.'.$extension)) {
//                 $filename = $originalName . ' ('.$index.')';
//                 $index++;
//             }

//             $path = $file->storeAs('uploads', $filename.'.'.$extension);

//             $fileData = new File;
//             $fileData->name = $filename;
//             $fileData->path = $path;
//             $fileData->file_id = Str::random(10);
//             $fileData->save();

//             $paths[] = $fileData;
//         }

//         // Возвращение путей к файлам
//         return $paths;
//     }

//     return "No files";
// });


// Route::addRoute("GET", "/files", function (Request $request) {
//     return File::all();
// });