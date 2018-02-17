<?php
namespace App\Http\Controllers\Match;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Lang;
use Carbon\Carbon;
use App\Player;
use App\Match;
use App\Game;
use App\GameRound;

class MatchController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return void
     */
    public function index()
    {
        return view('templates.match.index');
    }

    /**
     * Display the current Match
     *
     * @return void
     */
    public function activeMatch(Request $request, $matchId)
    {
        // Get the current Match
        $match = Match::findOrFail($matchId);

        // Check if Match has ended
        if (!empty($match->end_datetime)) {
            return redirect('match')->with('error', Lang::get('errors.match_has_ended'));
        }
        
        $unique_identifier = $request->session()->get('unique_identifier', '');

        // Check if this Match belongs to our current Session
        if (empty($unique_identifier) || $unique_identifier != $match->unique_identifier) {
            return redirect('match')->with('error', Lang::get('errors.match_in_progress'));
        }

        // Keep the result in a separate array to avoid calculations in the view
        $results = array();

        $match->players->each(function($player) use ($match, &$results) {
            $match->games->each(function($game) use (&$results, $player) {
                $results[$player->id][$game->number] = $this->calculateResult($game, $player);
            });
        });
        
        return view('templates.match.match', compact('match', 'results'));
    }

   /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     *
     * @return void
     */
    public function store(Request $request)
    {
        $this->validate($request, ['player_one' => 'required||min:1|max:80', 'player_two' => 'required||min:1|max:80']);

        // We don't want two players with the same name in a Match
        if ($request->get('player_one') == $request->get('player_two')) return redirect('match')->with('error', Lang::get('errors.different_names'));

        // Create our players
        $playerOneValues = array('name' => trim($request->get('player_one')));
        $playerOne = Player::create($playerOneValues); 

        $playerTwoValues = array('name' => trim($request->get('player_two')));
        $playerTwo = Player::create($playerTwoValues); 

        $now = Carbon::now('Europe/Stockholm');

        // To make it easy, since we validate length just set the name of the Match to the players names
        $matchName = $playerOne->name . ' - ' . $playerTwo->name;

        // Create Match
        $matchValues = array('name' => $matchName, 'unique_identifier' => md5(microtime()), 'winner_player_id' => 0, 'start_datetime' => $now->format('Y-m-d H:i:s'));
        $match = Match::create($matchValues);

        // Initialize the Match
        $this->initializeMatch($match, array($playerOne, $playerTwo));
       
        // Keep track of the Match identifier, just to prevent direct URL access for a Match in progress
        $request->session()->put('unique_identifier', $matchValues['unique_identifier']);

          // Go to the Match
        return redirect()->route('active_match', ['match_id' => $match->id]);
    }

    private function initializeMatch($match, $players) 
    {
        $numberOfFrames = 10;

        // Set players
        foreach($players as $player) {
            $match->assignPlayer($player);
        }

        // Since a Bowling Match has 10 Frames (Games), we will create 10 Games
        for ($x = 1; $x <= $numberOfFrames; $x++) {
            // Name and number is the same for now
            $gameValues = array('name' => $x, 'number' => $x, 'match_id' => $match->id);
            $game = Game::create($gameValues);

            // Create empty GameRounds, will make it simple to present the data.
            // Each player has two GameRounds / Frame (Game)
            $numberOfRounds = 2;

            // The last Game has 3 GameRounds
            if ($x == 10) $numberOfRounds = 3;
            
            foreach ($players as $player) {
                // Create each GameRound
                for ($i = 1; $i <= $numberOfRounds; $i++) {
                    $gameRound = GameRound::create( array('name' => $i, 'number' => $i, 'game_id' => $game->id, 'player_id' => $player->id));
                }
            }
        }
    }

    private function calculateResult($game, $player) 
    {
        $score = 0;

        $game->gameRounds->each(function($round) use ($player, &$score) {
            // The score belongs to Player
            if ($round->player_id == $player->id) {
                // The score is not default
                if ($round->created_at != $round->updated_at) {
                    // TODO handle strike, spare, violation

                    $score += $round->score;
                } else {
                     // Empty space default
                    $score = "&emsp;";
                }
            }
        });

        return $score;
    }
}