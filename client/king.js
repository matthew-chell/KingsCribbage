//contants
var server = '../server/index.php';

var createGame = 0;
var gameStatus = 1;
var boardSize = 2;
var getError = 3;
var getHand = 4;
var getPlayer = 5;
var joinGame = 7;
var numPlay = 8;
var play = 9;
var startGame = 10;
var getName = 11;

var notRunning = 0;
var running = 1;
var won = 2;

var squareSize = 50;
var update = 10000;

var width = 0;
var height = 0;

var errorMessage = 0;
var infoMessage = 1;

var lastGame = 0;

var fatalError = 0;

function getParameterByName(name) {
	name = name.replace(/[\[]/, "\\[").replace(/[\]]/, "\\]");
	var regex = new RegExp("[\\?&]" + name + "=([^&#]*)"),
	results = regex.exec(location.search);
	return results === null ? "" : decodeURIComponent(results[1].replace(/\+/g, " "));
}

function errorFun(xmlHttp, status, error){
	fatalError = 1;
	$("body").remove();
	$("html").html("Fatal API error: " + xmlHttp.status + "<br />" + xmlHttp.responseText);
}

function post(data, fun){
	console.log(data);
	$.ajax({
		type: 'POST',
		url: server,
		data: data,
		success: fun,
		error: errorFun,
		dataType:"json"
	});
}

function getPosition(x, y){
	return y / squareSize * width + x / squareSize;
}

function getXY(position){
	return {x:(position % width) * squareSize, y: Math.floor(position / width) * squareSize};
}

post({type: boardSize}, function(data){
	width = data.width;
	height = data.height;

	$("<style type='text/css'>" +
	"       .tile{" +
	"               width:" + squareSize +"px;" +
	"               height:" + squareSize + "px;" +
	"       }" +
	"       .board{" +
	"               width:" + (squareSize * width) + "px;" +
	"               height:" + (squareSize * height) + "px;" +
	"       }" +
	" </style>").appendTo("head");

});

var cgLock = false;
function checkGames(){
	if(!cgLock){
		cgLock = true;
		post({lastGame: lastGame}, function(data){
			for(g in data.games){
				var k = data.games[g].key;
				$("#lobby > tbody:last-child").append("<tr id='" + k +"'><td><a href='#" + k + "'>" + k + "</a></td>" +
						"<td><button onclick='newGame(\"" + k + "\")' ></button></td>" +
						"<td class='status'></td>" +
						"<td class='numP'></td></tr>");
				post({type: gameStatus, game: k}, function(data){
					var gk = parseQuery($(this).attr('data')).game;
					$("#" + gk + " button").html(data.name);
					$("#" + gk + " .status").html(data.status);
				});
				post({type: numPlay, game: k}, function(data){
					var gk = parseQuery($(this).attr('data')).game;
					$("#" + gk + " .numP").html(data.number);
				});
				lastGame = data.games[g].last;
			}
			cgLock = false;
		});
	}
	$("#lobby tr").each(function(){
		var k = $(this).attr('id');
		if("undefined" !== typeof k){
			post({type: gameStatus, game: k}, function(data){
				var gk = parseQuery($(this).attr('data')).game;
				$("#" + gk + " button").html(data.name);
				$("#" + gk + " .status").html(data.status);
			});
				post({type: numPlay, game: k}, function(data){
				var gk = parseQuery($(this).attr('data')).game;
				$("#" + gk + " .numP").html(data.number);
			});
		}
	});
}

$(document).ready(function() {
	$("#games").on("click",".board .tile", function(){
		var id = $(this).parent().parent().attr("id");
		games[id.split('_')[1]].clickBoard(this);
	});
	$("#games").on("click",".hand .tile", function(){
		var id = $(this).parent().parent().attr("id");
		games[id.split('_')[1]].clickHand(this);
	});
	enableCreate();
});

function enableCreate(){
	if(0 == width){
		console.log("waiting");
		if(! fatalError){
			setTimeout(enableCreate, 10);
		}
	} else {
		window.onhashchange = hash;
		hash();
		$(".dOnStart").removeAttr("disabled");
	}
}

function hash(){
	if (document.location.hash != ''){
		newGame(document.location.hash.substring(1));
	}
}

var games = [];
var errorCode = {};

function getErrorMessage(ec){
	if(!(ec in errorCode)){
		$.ajax({
			type: 'POST',
			url: server,
			data: {type: getError, error:ec},
			success:  function(data){
				errorCode[ec] = data.message;
			},
			error: errorFun,
			dataType: "json",
			async:false
		});
	}
	return errorCode[ec];
}

var ticking = false;
var ticker = null;
function startTick(){
	if(!ticking){
		ticker = setInterval(function(){
			console.log("tick");
			if (0 < games.length){
				var s = false;
				for(g in games){;
					if(null != games[g] && games[g].active && !games[g].lock){
						s = true;
						games[g].tick();
					}
				}
				if(!s){
					stopTick();
				}
                        }
		}, update);
		ticking = true;
	}
}

function stopTick(){
	if (ticking){
		clearInterval(ticker);
		ticking = false;
}	}

function newGame(gkey){
	games.push(new Game(games.length,gkey));
	startTick();
}

function createGame(name){
	post({type: createGame, name: name, hidden:0}, function(data){
		if(0 < data.error){
			alert(getErrorMessage(data.error));
		} else {
			newGame(data.key);
		}
	});
}

function Game(id, gkey){
	console.log("ng");
	var g = this;
	this.tick = function tick(){
		var g = this;
		g.lock = true;
		post({last: this.last, game: this.gkey}, function(data){
			for(mess in data.messages){
				var m = data.messages[mess];
				console.log(m);
				switch(m.location){
					case -1: //game over
						if(0 == $("#game_" + g.id + " .player.done").length){
							g.status = won;
							$("#game_" + g.id + " .status").html("Game Over");
							g.showMessage("Game Over", infoMessage);
							g.currentPlayer = -1;
							$("#game_" + g.id + " .play").attr("disabled", true);
						}
						var max = 0;
						for (var i = 0; $("#game_" + g.id + " .player").length > i; i ++){
							if(!$("#game_" + g.id + " .player_" + i).hasClass("done")){
								$("#game_" + g.id + " .player_" + i + " .score").html(m.score);
								$("#game_" + g.id + " .player_" + i).addClass("done");
								if(max < m.score){
									max = m.score;
								}
								break;
							}
							if(max < parseInt($("#game_" + g.id + " .player_" + i + " .score").html())){
								max = $("#game_" + g.id + " .player_" + i + " .score").html();
							}
						}
						if(0 == $("#game_" + g.id + " .player:not(.done)").length){
							//all done
							for (var i = 0; $("#game_" + g.id + " .player").length > i; i ++){
								if(max == parseInt($("#game_" + g.id + " .player_" + i + " .score").html())){
									//winner
									$("#game_" + g.id + " .player_" + i).addClass("winner");

									//todo you won?
								}
							}
							g.active = false;

						}
						break;
					case -2: //player join
						$("#game_" + g.id + " .table > tbody:last-child").append("<tr class='player player_" + $("#game_" + g.id + " .player").length + "' >" +
							"<td class='name" + (g.number == $("#game_" + g.id + " .player").length ? " you" : "") +"'></td><td class='score'>0</td></tr>");
						post({type: getName, game: g.gkey, player:  $("#game_" + g.id + " .player").length - 1}, function(data){
							var pn = parseInt(parseQuery($(this).attr('data')).player);
							$("#game_" + g.id + " .player_" + pn + " .name").html(data.name);
						});
						break;
					case -3: //game started
					case -4:
					case -5:
					case -6:
						g.status = running;
						g.currentPlayer = m.location + 6;
						g.showMessage("Game Started", infoMessage);
						$('#game_' + g.id + " .status").html("Running");
						if ("undefined" !== typeof g.pkey && 0 < g.pkey.length){
							g.getHand();
						}
						$("#game_" + g.id + " .startGame").remove();
						$("#game_" + g.id + " .player_" + g.currentPlayer).addClass("current");

						if(g.currentPlayer == g.number){
							g.showMessage("Your turn", infoMessage);
						} else {
							g.showMessage($("#game_" + g.id + " .player_" + g.currentPlayer + " .name").html() + "'s turn", infoMessage);
						}
						break;
					default:
						//tiles;
						$("#game_" + g.id + " .player_" + g.currentPlayer + " .score").html(
							parseInt($("#game_" + g.id + " .player_" + g.currentPlayer + " .score").html()) + m.score);
						if (0 < m.score){
							if(g.number == g.currentPlayer){
								$("#game_" + g.id + " .hand .ghost").remove();
								$("#game_" + g.id + " .ghost").removeClass("ghost light dark").html("");
								//get new tiles
								g.getHand();
							}
							var i = 0;
							var loc = getXY(m.location).x;
							var getTile = function(){
								return g.getTileAt(loc, getXY(m.location).y);
							};
							if(1 == m.down){
								loc = getXY(m.location).y;
								getTile = function(){
									return g.getTileAt(getXY(m.location).x, loc);
								};
							}
							while(i < m.tiles.length){
								var t = getTile();
								if("" == $(t).html()){
									$(t).addClass('1' == m.tiles.substring(i + 1, i + 2) ? 'dark' : 'light');
									$(t).html(m.tiles.substring(i, i + 1));
									if('1' == $(t).html()){
										$(t).html('10');
									}
									i += 2;
								}
								loc += squareSize;
							};
						}
						g.currentPlayer ++;
						g.currentPlayer %= $("#game_" + g.id + " .player").length;
						$("#game_" + g.id + " .current").removeClass("current");
						$("#game_" + g.id + " .player_" + g.currentPlayer).addClass("current");
						if(g.currentPlayer == g.number){
							g.showMessage("Your turn", infoMessage);
							$("#game_" + g.id + " .play").removeAttr("disabled");
						} else {
							g.showMessage($("#game_" + g.id + " .player_" + g.currentPlayer + " .name").html() + "'s turn", infoMessage);
							$("#game_" + g.id + " .play").attr("disabled", true);
						}
						break;
				}
				g.last = m.last;
			}
			g.lock = false;
		});
	};
	this.clickHand = function(t){
		if(!$(t).hasClass("ghost")){
			if($(t).hasClass("k-selected")){
				if("6" == $(t).html()){
					$(t).html("9");
				} else if ("9" == $(t).html()){
					$(t).html("6");
				} else {
					$(t).removeClass("k-selected");
				}
			} else if (0 == $("#game_" + g.id + " .hand .k-selected:not(.ghost)").length){
				$(t).addClass("k-selected");
			} else {
				var s = $("#game_" + g.id + " .hand .k-selected");
				var h = $(t).html();
				var d = $(t).hasClass("dark");
				$(t).html($(s).html());
				$(t).removeClass("light dark");
				if($(s).hasClass("dark")){
					$(t).addClass("dark");
				} else {
					$(t).addClass("light");
				}
				$(s).html(h);
				$(s).removeClass("light dark");
				if(d){
					$(s).addClass("dark");
				} else {
					$(s).addClass("light");
				}
				$(s).removeClass("k-selected");
			}
		}
	};
	this.clickBoard = function(t, x, y){
		if("" == $(t).html() && 1 == $("#game_" + g.id + " .hand .k-selected:not(.ghost)").length){
			var s = $("#game_" + g.id + " .hand .k-selected");
			if($(s).hasClass("dark")){
				$(t).addClass("dark");
			} else {
				$(t).addClass("light");
			}
			$(t).html($(s).html());
			$(t).addClass("ghost");
			$(s).addClass("ghost").removeClass("k-selected");
			$(s).nextAll(":not(.ghost):first").addClass("k-selected");
		} else if ($(t).hasClass("ghost")){
			$("#game_" + g.id + " .hand .k-selected").removeClass("k-selected");
			var f = false;
			$("#game_" + g.id + " .hand").children().each(function(){
				if (!f && $(this).hasClass("ghost") &&
						$(this).html() == $(t).html() &&
						(($(this).hasClass("dark") && $(t).hasClass("dark")) ||
						($(this).hasClass("light") && $(t).hasClass("light")))){
					f = true;
					$(this).addClass("k-selected").removeClass("ghost");
					$(t).removeClass("ghost dark light").html("");
				}
			});
		}
	};
	this.drawGame = function(){
		var g = this;
		var a =
			"<div id='game_" + this.id + "' class='game'>" +
				"<button class='btnPlayer' onclick='games[" + this.id + "].createPlayer($(\"#game_" + this.id + " .createPlayer\").val());'>Create Player</button>" +
				"<input class='createPlayer' type='text' placeholder='Name'/>" +
				"<button class='btnPlayer'onclick='games[" + this.id + "].joinPlayer($(\"#game_" + this.id + " .joinPlayer\").val());'>Connect to Player</button>" +
				"<input class='joinPlayer' type='text' placeholder='Player Key'/><br />" +
				"<button class='startGame' onclick='games[" + this.id + "].startGame();'>Start Game</button><button onclick='games[" + this.id +"].deleteGame();'>Remove Game</button><br />" +
				"<table class='table'>" +
					"<tr><th><a class='gname' href='#" + g.gkey + "'></a></th><th class='status'>Not Running</th><tr>" +
				"</table>" +
				"<div class='log'></div>" +
				"<div class='board'>";
		var c = {x: Math.floor(width / 2), y: Math.floor(height / 2)};
		for(var i = 0; i < width; i ++){
			for (var j = 0; j < height; j ++){
				a +=	"<div class='tile " + (c.x == i && c.y == j ? "centre" : "") + "' style='left:" + (i * squareSize) + "px; top:" + (j * squareSize) + "px;'></div>";
			}
		}
		a +=		"</div>" +
			"</div>";
		$("#games").append(a);
		 post({type: gameStatus, game: this.gkey}, function(data){
			$("#game_" + g.id + " .gname").html(data.name);
		});
	};
	this.createPlayer = function(n){
		var g = this;
		post({type: joinGame,game: this.gkey, name: n}, function(data){
			if(0 < data.error){
				g.showMessage(getErrorMessage(data.error), errorMessage);
			} else {
				g.joinPlayer(data.key);
				$("#game_" + g.id + " .joinPlayer").val(data.key);
			}
		});
	};
	this.joinPlayer = function(pkey){
		this.pkey = pkey;
		var g = this;

		$("#game_" + this.id + " .table").after("<div class='hand'></div><button class='play' onclick='games[" + this.id + "].play();' disabled='disabled'>Play</button>");
		$("#game_" + this.id + " .joinPlayer").after("<button class='remove' onclick='games["+ this.id + "].disconnectPlayer()'>Disconnect</button>");
		$("#game_" + this.id + " .btnPlayer").attr("disabled", true);

		post({type: getPlayer, player: this.pkey}, function(data){
			if(null == data.number){
				g.showMessage("No player found: " + g.pkey, errorMessage);
				$("#game_" + g.id + " .hand").remove();
				$("#game_" + g.id + " .play").remove();
				return;
			}
			g.number = data.number;
			$("#game_" + g.id + " .player_" + g.number + " .name").addClass("you");
			if(g.currentPlayer == g.number){
				$("#game_" + g.id + " .play").removeAttr("disabled");
			}
		});
		if (g.status == running){
			g.getHand();
		}
	};
	this.disconnectPlayer = function(){
		$("#game_" + this.id + " .hand").remove();
		$("#game_" + this.id + " .play").remove();
		$("#game_" + this.id + " .you").removeClass("you");
		$("#game_" + this.id + " .ghost").remove();
		$("#game_" + this.id + " .remove").remove();
		$("#game_" + this.id + " .btnPlayer").removeAttr("disabled");
	}
	this.startGame = function(){
		var g = this;
		post({type: startGame, game: this.gkey}, function(data){
			if(0 < data.error){
				g.showMessage(getErrorMessage(data.error), errorMessage);
			}
		});
	};
	this.getHand = function(){
		var g = this;
		post({type:getHand, player: this.pkey}, function(data){
			// find the delta in hand
			var u = [];
			$("#game_" + g.id + " .hand").children().each(function(){
				u.push(false);
			});
			// += 2 because each tile is two characters
			for(var i = 0; i < data.hand.length; i +=2){
				var v = data.hand.substring(i, i + 1);
				var d = ('1' == data.hand.substring(i + 1, i + 2));
				if ('1' == v) {
					v = '10';
				}
				var f = false;
				var j = 0;
				$("#game_" + g.id + " .hand").children().each(function(){
					//check if hand already has tile
					if(!f && !u[j] && d == $(this).hasClass('dark') && (v == $(this).html() || (v == '6' && $(this).html() == '9') || (v == '9' && $(this).html() == '6'))){
						u[j] = true;
						f = true;
					}
					j ++;
				});
				if(!f){
					var c = "light";
					if(d){
						c = "dark"
					}
					$("#game_" + g.id + " .hand").append("<div class='tile " + c + "'>" + v + "</div>");
					u.push(true);
				}
			}
		});
	};
	this.play = function(){
		var g = this;
		var tiles = [];
		$("#game_" + this.id + " .board .ghost").each(function(){
			tiles.push({
				top: $(this).position().top,
				left: $(this).position().left,
				value: $(this).html().charAt(0),
				dark: ($(this).hasClass('dark') ? '1' : '0')
			});
		});
		if(0 == tiles.length){
			if(confirm("Are you sure you what to pass?")){
				post({type: play, player: this.pkey, tiles: "", location: 0, down: 0}, function(data){
					if(0 < data.error){
						g.showMessage(getErrorMessage(data.error), errorMessage);
					}
				});

			}
		} else if (1 == tiles.length){
			post({type: play, player: this.pkey, tiles: tiles[0].value + tiles[0].dark, location: getPosition(tiles[0].left, tiles[0].top), down:0}, function(data){
				if(0 < data.error){
					g.showMessage(getErrorMessage(data.error), errorMessage);
				}
			});
		} else {
			var mtop = height + 1;
			var mleft = width + 1;
			var direction = 0; //1=down 2=across
			for (t in tiles){
				if(mtop < tiles[t].top){
					mtop = tiles[t].top;
				} else if(mtop == tiles[t].top){
					if (0 == direction) {
						direction = 2;
					}
				}
				if(mleft < tiles[t].left){
					mleft = tiles[t].left;
				} else if(mleft == tiles[t].left){
					if (0 == direction) {
						direction = 1;
					}
				}
				if((1 == direction && mleft != tiles[t].left) ||
						(2 == direction && mtop != tiles[t].top)){
					this.showMessage("Tiles are not in one row or column", errorMessage);
					return;
				}
			}
			var getTile;
			var loc;
			switch(direction){
				case 0:
					this.showMessage("Tiles are not in one row or column", errorMessage);
					return;
				case 1:
					tiles.sort(function(a, b){return a.top - b.top});
					getTile = function(){
						return g.getTileAt(tiles[0].left,loc);
					};
					loc = tiles[0].top;
					break;
				case 2:
					tiles.sort(function(a, b){return a.left - b.left});
					getTile = function(){
						return g.getTileAt(loc, tiles[0].top);
					};
					loc = tiles[0].left;
					break;
			}
			//look for gaps
			var i = 0;
			var st = "";
			while(i < tiles.length){
				var t = getTile();
				if("" == $(t).html()){
					//current space on board has nothing
					this.showMessage("Gap in played tiles", errorMessage);
					return;
				} else {
					//go to next space on board
					if($(t).hasClass("ghost")){
						st += tiles[i].value + tiles[i].dark;
						i++;
					}
					loc += squareSize;
				}
			}
			post({type: play, player: this.pkey, tiles:st, location: getPosition(tiles[0].left, tiles[0].top), down: 2 - direction}, function(data){
				if(0 < data.error){
					g.showMessage(getErrorMessage(data.error), errorMessage);
				}
			});
		}

	};
	this.showMessage = function(message, type){
		var id = 'r' + parseInt(Math.random() * 10000000000000);
		var c;
		switch(type){
			case infoMessage:
				c = 'info';
				break;
			case errorMessage:
				c = 'error';
				break;
		}
		$("#game_" + g.id + " .log").append("<div id='" + id + "' class='message " + c + "'>" + message + "</div>");
		$('#' + id).hide().fadeIn();
		$('#' + id).click(function(){$('#' + id).fadeOut().remove();});
		setTimeout(function(){$('#' + id).fadeOut().remove();}, 2000);
	};
	this.getTileAt = function(x, y){
		var t;
		$("#game_" + this.id + " .board  .tile").each(function(){
			if($(this).position().left == x && $(this).position().top == y){
				t = this;
			}
		});
		return t;
	}
	this.deleteGame = function(){
		$("#game_" + this.id).remove();
		games[this.id] = null;
	}

	// todo get gkey from pkey;
	this.last = 0;
	this.id = id;
	this.gkey = gkey;
	this.pkey;
	this.active = true;
	this.lock = false;
	this.currentPlayer = -1;
	this.status = notRunning;

	//if playing
	this.number = -1;

	this.drawGame();
}

function parseQuery(qstr) {
	var query = {};
	var a = qstr.split('&');
	for (var i = 0; i < a.length; i++) {
		var b = a[i].split('=');
	        query[decodeURIComponent(b[0])] = decodeURIComponent(b[1] || '');
	}
	return query;
}
