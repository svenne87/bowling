<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Match;

class WelcomeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return void
     */
    public function index()
    {
        $perPage = 20;
        $matches = Match::orderBy('winner_score', 'DESC')->paginate($perPage);
        return view('templates.welcome.index', compact('matches'));
    }
}