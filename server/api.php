<?php
$fun = array(
	//0-create game
	array(
		'n' => 'doCreateGame',
		'c' => 4,
		'p' => array(
			'name' => array(
				'r' => true,
				'p' => 0
			),
			'hidden' => array(
				'r' => false,
				'p' => 1
			)
		),
		'r' => array(
			'key' => 2,
			'error' => 3
		)
	),
	//1-game status
	array(
		'n' => 'getGameStatus',
		'c' => 8,
		'p' => array(
			'game' => array(
				'r' => true,
				'p' => 0
			)
		),
		'r' => array(
			'status' => 1,
			'currentPlayer' => 2,
			'player0' => 3,
			'player1' => 4,
			'player2' => 5,
			'player3' => 6,
			'name' => 7
		)
	),
	//2-board size
	array(
		'n' =>'getBoardSize',
		'c' => 2,
		'p' => array(),
		'r' => array(
			'width' => 0,
			'height' => 1
		)
	),
	//3-get error
	array(
		'n' => 'getError',
		'c' => 2,
		'p' => 'error',
		'r' => 'message'
	),
	//4-get hand
	array(
		'n' => 'getHand',
		'p' => 'player',
		'r' => 'hand'
	),
	//5-get player number
	array(
		'n' => 'getPlayerNum',
		'p' => 'player',
		'r' => 'number'
	),
	//6-get tile at
	array(
		'n' => 'getTileAt',
		'c' => 4,
		'p' => array(
			'game' => array(
				'r' => true,
				'p' => 0
			),
			'location' => array(
				'r' => true,
				'p' => 1
			)
		),
		'r' => array(
			'tile' => 2,
			'dark' => 3
		)
	),
	//7-join game
	array(
		'n' => 'doJoinGame',
		'c' =>4,
		'p' => array(
			'game' => array(
				'r' => true,
				'p' => 0
			),
			'name' => array(
				'r' => true,
				'p' => 1
			)
		),
		'r' => array(
			'player' => 2,
			'error' => 3
		)
	),
	//8-get number of players
	array(
		'n' => 'getNumOfPlayers',
		'p' => 'game',
		'r' => 'number'
	),
	//9-play
	array(
		'n' => 'doPlay',
		'c' => '5',
		'p' => array(
			'player' => array(
				'r' => true,
				'p' => 0
			),
			'tiles' => array(
				'r' => true,
				'p' => 1
			),
			'location' => array(
				'r' => true,
				'p' => 2
			),
			'down' => array(
				'r' => true,
				'p' => 3
			)
		),
		'r' => array(
			'error' => 4
		)
	),
	//10-start gane
	array(
		'n' => 'doStartGame',
		'p' => 'game',
		'r' => 'error'
	),
	//11-get name
	array(
		'n' => 'getName',
		'c' => 3,
		'p' => array(
			'game' => array(
				'r' => true,
				'p' => 0
			),
			'player' => array(
				'r' => true,
				'p' => 1
			)
		),
		'r' => array(
			'name' => 2
		)
	),
	//12-get last
	array(
		'n' => 'getLast',
		'p' => 'game',
		'r' => 'last'
	)
);
