<?php
function initDatabase(){
	global $kng_con, $r;

	$sql = array();

	//table constant_int
	$sql[] = "DROP TABLE IF EXISTS `constant_int`;";
	$sql[] = "CREATE TABLE `constant_int` (
		`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
		`group` varchar(20) NOT NULL,
		`key` varchar(25) NOT NULL,
		`value` varchar(45) NOT NULL,
	PRIMARY KEY (`id`)
	) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1;";
	$sql[] = "INSERT INTO `constant_int` VALUES
		(1,'board_size','width','15'),
		(2,'board_size','height','15');";

	//table error
	$sql[] = "DROP TABLE IF EXISTS `error`;";
	$sql[] = "CREATE TABLE `error` (
		`id` int(11) NOT NULL AUTO_INCREMENT,
		`message` varchar(100) DEFAULT NULL,
	PRIMARY KEY (`id`)
	) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=latin1;";
	$sql[] = "INSERT INTO `error` VALUES
		(1,'too few players'),
		(2,'game not running'),
		(3,'not your turn'),
		(4,'game has started'),
		(5,'too many players'),
		(6,'do not have tile'),
		(7,'off board'),
		(8,'too long row'),
		(9,'not all used'),
		(10,'not touching'),
		(11,'not at centre'),
		(12,'dup name'),
		(13,'invalid character in name');";

	//table game
	$sql[] = "DROP TABLE IF EXISTS `game`;";
	$sql[] = "CREATE TABLE `game` (
		`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
		`key` varchar(32) NOT NULL,
		`hidden` bit(1) NOT NULL,
		`currentPlayer` tinyint(10) NOT NULL,
		`status` enum('notRunning','running','won','') NOT NULL,
		`name` varchar(50) NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `key` (`key`),
	UNIQUE KEY `name_UNIQUE` (`name`),
	KEY `currentPlayer` (`currentPlayer`)
	) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;";

	//table message
	$sql[] = "DROP TABLE IF EXISTS `message`;";
	$sql[] = "CREATE TABLE `message` (
		`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
		`tiles` varchar(10) DEFAULT NULL,
		`time` datetime NOT NULL,
		`game_id` int(10) unsigned NOT NULL,
		`location` int(11) NOT NULL,
		`down` varchar(1) DEFAULT '0',
		`score` int(11) DEFAULT '0',
	PRIMARY KEY (`id`),
	KEY `game_id` (`game_id`)
	) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;";

	//table player
	$sql[] = "DROP TABLE IF EXISTS `player`;";
	$sql[] = "CREATE TABLE `player` (
		`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
		`game_id` int(10) unsigned NOT NULL,
		`score` int(10) unsigned NOT NULL,
		`name` varchar(100) NOT NULL,
		`key` varchar(32) NOT NULL,
		`passed` bit(1) NOT NULL DEFAULT b'0',
	PRIMARY KEY (`id`),
	UNIQUE KEY `key` (`key`),
	KEY `game_id` (`game_id`),
	KEY `name` (`name`)
	) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;";

	//table tile
	$sql[] = "DROP TABLE IF EXISTS `tile`;";
	$sql[] = "CREATE TABLE `tile` (
		`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
		`value` varchar(1) NOT NULL,
		`dark` bit(1) NOT NULL,
	PRIMARY KEY (`id`)
	) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;";

	//table tile_join
	$sql[] = "DROP TABLE IF EXISTS `tile_join`;";
	$sql[] = "CREATE TABLE `tile_join` (
		`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
		`tile_id` int(10) unsigned NOT NULL,
		`game_id` int(10) unsigned DEFAULT NULL,
		`player_id` int(10) unsigned DEFAULT NULL,
		`location` int(10) unsigned DEFAULT NULL,
	PRIMARY KEY (`id`),
	KEY `tile_id` (`tile_id`),
	KEY `game_id` (`game_id`),
	KEY `player_id` (`player_id`)
	) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;";

	//fun getAgainst
	$sql[] = "DROP function IF EXISTS `_getAgainst` ;";
	$sql[] = "CREATE DEFINER=`root`@`%` FUNCTION `_getAgainst`(`v`varchar(1)) RETURNS int(11)
	BEGIN
	RETURN case v
		when 'A' then 1
		when 'K' then 10
		when 'Q' then 10
		when 'J' then 10
		when '1' then 10
		else v end;
	END ;;";

	//fun getOrder
	$sql[] = "DROP function IF EXISTS `_getOrder` ;";
	$sql[] = "CREATE DEFINER=`root`@`%` FUNCTION `_getOrder`(`v`varchar(1)) RETURNS int(11)
	BEGIN
	RETURN case v
		when 'A' then 1
		when 'K' then 13
		when 'Q' then 12
		when 'J' then 11
		when '1' then 10
		else v end;
	END ;;";

	//fun getPosition
	$sql[] = "DROP function IF EXISTS `_getPosition` ;";
	$sql[] = "CREATE DEFINER=`root`@`localhost` FUNCTION `_getPosition`(`x` TINYINT UNSIGNED, `y` TINYINT UNSIGNED) RETURNS tinyint(4)
	return y * (select `value` from `constant_int` where `group` = 'board_size' and `key` = 'width') + x ;;";

	//fun getX
	$sql[] = "DROP function IF EXISTS `_getX` ;";
	$sql[] = "CREATE DEFINER=`root`@`localhost` FUNCTION `_getX`(`position` TINYINT UNSIGNED) RETURNS tinyint(10) unsigned
	return mod(position,(select `value` from `constant_int` where `group` = 'board_size' and `key` = 'width')) ;;";

	//fun getY
	$sql[] = "DROP function IF EXISTS `_getY` ;";
	$sql[] = "CREATE DEFINER=`root`@`localhost` FUNCTION `_getY`(`position` TINYINT UNSIGNED) RETURNS tinyint(11)
	return floor(position / (select `value` from `constant_int` where `group` = 'board_size' and `key` = 'width')) ;;";

	//proc doCreateGame
	$sql[] = "DROP procedure IF EXISTS `doCreateGame` ;";
	$sql[] = "CREATE DEFINER=`root`@`%` PROCEDURE `doCreateGame`(in nam varchar(50), in h varchar(1), out  gk varchar(32), OUT `errorCode` INT UNSIGNED)
	BEGIN
	set errorCode = 0;
	set gk = LEFT(UUID(), 32);
	set @h = 1;
	if h = '0' then
		set @h = 0;
	end if;
	if 1 = (SELECT CASE WHEN nam REGEXP '^[A-Za-z0-9 ]+$' then 1 else 0 end) then
		set errorCode = 13;
	else
		if 0 = (SELECT count(`id`) from `game` where `name` = nam) then
			INSERT INTO `game` (`id`, `key`, `hidden`, `name`) VALUES (null, gk, @h, nam);
		else
			set errorCode = 12;
		end if;
	end if;
	END ;;";

	//proc doJoinGame
	$sql[] = "DROP procedure IF EXISTS `doJoinGame` ;";
	$sql[] = "CREATE DEFINER=`root`@`%` PROCEDURE `doJoinGame`(IN `g` varchar(32), IN `nam` varchar(50), OUT `pk` varchar(32), OUT `errorCode` INT UNSIGNED)
	BEGIN
	set errorCode = 0;
	set @gid =  (select `id` from `game` where `key` = g);
	set pk = '';
	if 'notRunning' != (select `status` from `game` where `id` = @gid) then
		set errorCode = 4;
	else
		if 1 = (SELECT CASE WHEN nam REGEXP '^[A-Za-z0-9 ]+$' then 1 else 0 end) then
			set errorCode = 13;
		else
			if 4 = (SELECT count(`id`) from `player` where `game_id` = @gid) then
				set errorCode = 5;
			else
				if 0 = (SELECT count(`id`) from `player` where `name` = nam and `game_id` = @gid) then
					set errorCode = 0;
					set pk = LEFT(UUID(), 32);
					insert into `player` (`id`, `key`, `game_id`, `name`) value (null, pk, @gid, nam);

					INSERT INTO `message`(`id`, `tiles`, `time`, `game_id`, `location` , `down`, `score`) 
						VALUES (null, null, now(), @gid, -2, null, null);
				else
					set errorCode = 12;
				end if;
			end if;
		end if;
	end if;
	END ;;";

	//proc doPlay
	$sql[] = "DROP procedure IF EXISTS `doPlay` ;";
	$sql[] = "CREATE DEFINER=`root`@`%` PROCEDURE `doPlay`(IN `play` varchar(32), IN `tiles` varchar(10), IN `loc` int UNSIGNED, IN `dow` varchar(1), OUT `errorCode` int UNSIGNED)
	BEGIN
	SET @gid = (SELECT `game_id` FROM `player` WHERE `key` = play);

	if 'running' != (SELECT `status` FROM `game` WHERE `id` = @gid) then
		set errorCode = 2;
	else
		CALL _getPlayer((SELECT `currentPlayer` FROM `game` WHERE `id` = @gid), @gid, @cp);

		SET @pid = (SELECT `id` FROM `player` WHERE `key` = play);
		if @cp != @pid then
			set errorCode = 3;
		else
			set errorCode = 0;
			-- all the checks are done
			set @score = 0;

			if 0 < CHAR_LENGTH(tiles) then
				set @delta = (select case when dow = 1 then (select `value` from `constant_int` where `group` = 'board_size' and `key` = 'width') else 1 end);
				set @p_delta = (select case when dow = 1 then 1 else (select `value` from `constant_int` where `group` = 'board_size' and `key` = 'width') end);
				set errorCode = 0;
				set @i = 0;
				set @touchOther = 0;
				set @t = loc;

				til: loop
					if CHAR_LENGTH(tiles) = @i then
						leave til;
					end if;

					-- check if have the tiles in hand
					set @tid = (select tj.id
						from `tile_join` tj
						join `tile` t
							on tj.tile_id = t.id
								and `player_id` = @pid
								and `game_id` is null -- has not been placed yet
								and case t.value when '9' then '6' else t.value end =
									case substring(tiles, @i + 1, 1) when '9' then '6' else substring(tiles, @i + 1, 1) end
								and t.dark = substring(tiles, @i + 2, 1) Limit 1);
					if @tid is null then
						set errorCode = 6;
						leave til;
					end if;

					-- this loop places the tiles in empty spaces on the board
					locate: loop

						if (select `id` from `tile_join` where `game_id` = @gid and `location` = @t) is null then
							update `tile_join` set `game_id` = @gid, `location` = @t where `id` = @tid;
							set @t = @t + @delta;
							leave locate;
						end if;

						-- checks to make sure that the next piece will not be place off the board
						if dow = 1 then
							if (select `value` from `constant_int` where `group` = 'board_size' and `key` = 'height') = _getY(@t) + 1 then
								set errorCode = 7;
								leave til;
							end if;
						else
							if (select `value` from `constant_int` where `group` = 'board_size' and `key` = 'width') = _getX(@t) + 1 then
								set errorCode = 7;
								leave til;
							end if;
						end if;

						set @t = @t + @delta;
						set @touchOther = 1;
					end loop locate;

					-- select the played tile in the top left most corner
					set @cl = @t - @delta;

					oneSide: loop
						if 0 = (SELECT count(`id`) from `tile_join` where `game_id` = @gid and `location` = @cl - @p_delta) then
							-- at the end of a row;
							leave oneSide;
						end if;

						if dow = 0 then
							if 0 = _getY(@cl) then

								-- at the end of then board;
								leave oneSide;
							end if;
						else
							if 0 = _getX(@cl) then
								-- at the end of then board;
								leave oneSide;
							end if;
						end if;
						set @touchOther = 1;
						set @cl = @cl - @p_delta;
					end loop oneSide;

					-- goes to the other side
					set @t0 = (select `tile_id` from `tile_join` where  `game_id` = @gid and `location` = @cl);
					set @t1 = null, @t2 = null, @t3 = null, @t4 = null;
					otherSide: loop

						if 0 = (SELECT count(`id`) from `tile_join` where `game_id` = @gid and `location` = @cl + @p_delta) then
							-- at the end of a row;
							leave otherSide;
						end if;
						if dow = 0 then
							if (select `value` from `constant_int` where `group` = 'board_size' and `key` = 'height') = _getY(@cl) then

								-- at the end of then board;
								leave otherSide;
							else
								if _getY(@cl + @p_delta) > _getY(loc) then
									set @touchOther = 1;
								end if;
							end if;
						else
							if (select `value` from `constant_int` where `group` = 'board_size' and `key` = 'width') = _getX(@cl) then
								-- at the end of then board;
								leave otherSide;
							else
								if _getX(@cl + @p_delta) > _getX(loc) then
									set @touchOther = 1;
								end if;
							end if;
						end if;

						set @cl = @cl + @p_delta;
						if @t1 is null then
							set @t1 = (select `tile_id` from `tile_join` where  `game_id` = @gid and `location` = @cl);
						else
							if @t2 is null then
								set @t2 = (select `tile_id` from `tile_join` where  `game_id` = @gid and `location` = @cl);
							else
								if @t3 is null then
									set @t3 = (select `tile_id` from `tile_join` where  `game_id` = @gid and `location` = @cl);
								else
									if @t4 is null then
										set @t4 = (select `tile_id` from `tile_join` where  `game_id` = @gid and `location` = @cl);
									else
										set errorCode = 8;
										leave til;
									end if;
								end if;
							end if;
						end if;

						-- more than one wide
					end loop otherSide;

					if @t1 is not null then

						call _getScore(@t0, @t1, @t2, @t3, @t4, @s);
						if 0 = @s then
							set errorCode = 9;
							leave til;
						else
							set @score = @score + @s;
						end if;
					end if;

					-- two because each tile is two characters
					set @i = @i + 2;
				end loop til;

				if 0 = errorCode then
					-- now for inline;
					-- select the played tile in the top left most corner
					set @cl = (select `location` from `tile_join` where `game_id` = @gid and `player_id` is not null order by `location` ASC limit 1);

					oneSide: loop
						if 0 = (SELECT count(`id`) from `tile_join` where `game_id` = @gid and `location` = @cl - @delta) then
							-- at the end of a row;
							leave oneSide;
						end if;

						if dow = 1 then
							if 0 = _getY(@cl) then
								-- at the end of then board;
								leave oneSide;
							end if;
						else
							if 0 = _getX(@cl) then
								-- at the end of then board;
								leave oneSide;
							end if;
						end if;

						set @touchOther = 1;
						set @cl = @cl - @delta;
					end loop oneSide;

					-- goes to the other side
					set @t0 = (select `tile_id` from `tile_join` where  `game_id` = @gid and `location` = @cl);
					set @t1 = null, @t2 = null, @t3 = null, @t4 = null;
					otherSide: loop

						-- get row check for longer then six
						if 0 = (SELECT count(`id`) from `tile_join` where `game_id` = @gid and `location` = @cl + @delta) then
							-- at the end of a row;
							leave otherSide;
						end if;
						if dow = 1 then
							if (select `value` from `constant_int` where `group` = 'board_size' and `key` = 'height') = _getY(@cl) then

								-- at the end of then board;
								leave otherSide;
							else
								if _getY(@cl + @delta) > _getY(loc + @delta * (CHAR_LENGTH(tiles) / 2 - 1)) then
									set @touchOther = 1;
								end if;
							end if;
						else
							if (select `value` from `constant_int` where `group` = 'board_size' and `key` = 'width') = _getX(@cl) then
								-- at the end of then board;
								leave otherSide;
							else
								if _getX(@cl + @delta) > _getX(loc + @delta * (CHAR_LENGTH(tiles) / 2 - 1)) then
									set @touchOther = 1;
								end if;
							end if;
						end if;
						set @cl = @cl + @delta;
						if @t1 is null then
							set @t1 = (select `tile_id` from `tile_join` where  `game_id` = @gid and `location` = @cl);
						else
							if @t2 is null then
								set @t2 = (select `tile_id` from `tile_join` where  `game_id` = @gid and `location` = @cl);
							else
								if @t3 is null then
									set @t3 = (select `tile_id` from `tile_join` where  `game_id` = @gid and `location` = @cl);
								else
									if @t4 is null then
										set @t4 = (select `tile_id` from `tile_join` where  `game_id` = @gid and `location` = @cl);
									else
										set errorCode = 8;
										leave otherSide;
									end if;
								end if;
							end if;
						end if;
					end loop otherSide;

					if 0 = errorCode then
						if 0 = @touchOther then
							if 0 = (select count(`id`) from `tile_join` where `game_id` = @gid and `player_id` is null) then
								-- was not any other tiles played already
								set @centre = _getPosition(floor((select `value` from `constant_int` where `group` = 'board_size' and `key` = 'width') / 2), 
									floor((select `value` from `constant_int` where `group` = 'board_size' and `key` = 'height') / 2) );

								-- see if aleast one tile is at centre
								if 0 = (select count(`id`) from `tile_join` where `game_id` = @gid and `player_id` is not null and `location` = @centre) then
									set errorCode = 11;
								else
									if 1 = (select count(`id`) from `tile_join` where `game_id` = @gid and `player_id` is not null) then
										set errorCode = 9;
									end if;
								end if;
							else
								if 0 = errorCode then
									set errorCode = 10;
								end if;
							end if;
						end if;

						if @t1 is not null then
							call _getScore(@t0, @t1, @t2, @t3, @t4, @s);
							if 0 = @s then
								if 0 = errorCode then
									set errorCode = 9;
								end if;
							else
								set @score = @score + @s;
							end if;
						end if;
					end if;
				end if;
			end if;

			if 0 = errorCode then
				update `player` set `passed` = case when CHAR_LENGTH(tiles) > 0 then 0 else 1 end, `score` = `score` + @score where `key` = play;
				if 5 = (select count(`id`) from `tile_join` where `game_id` = @gid and `player_id` is not null) then
					-- played all five;
					set @score = @score + 10;
				end if;
				update `tile_join` set `player_id` = null where `game_id` = @gid and `player_id` is not null;
				-- player draws new tiles;
				CALL `_fillUpHand` (@pid);
				INSERT INTO `message`(`id`, `tiles`, `time`, `game_id`, `location` , `down`, `score`)
					VALUES (null, tiles, now(), @gid, loc, dow, @score);
				call _endTurn(@gid);
			else
				-- there was an error; remove attempted tiles played from board
				update `tile_join` set `location` = null, `game_id` = null where `game_id` = @gid and `player_id` is not null;
			end if;
		end if;
	end if;
	END ;;";

	//proc doStartGame
	$sql[] = "DROP procedure IF EXISTS `doStartGame` ;";
	$sql[] = "CREATE DEFINER=`root`@`%` PROCEDURE `doStartGame`(IN `game` varchar(32) , OUT `errorCode` int(10) UNSIGNED)
	begin
	set @gid = (select `id` from `game` where `key` = game);
	set @num = (SELECT count(`id`) FROM `player` WHERE `game_id` = @gid);
	if 2 > @num then
		set errorCode = 1;
	else
		if 'running' = (select `status` from `game` where `id` = @gid) then
			set errorCode = 4;
		else
			delete from `tile_join` where `game_id` = @gid;
			-- delete from `message` where `game_id` = @gid;
			update `player` set `score` = 0 where `game_id` = @gid;
			SET @player = 0;
			setPlayer: LOOP
				IF @player = @num THEN
					LEAVE setPlayer;
				end if;
				CALL `_getPlayer` (@player, @gid, @id);
				CALL `_fillUpHand` (@id);
				set @player = @player + 1;
			END LOOP setPlayer;
			update `game` set `status` = 'running', `currentPlayer` = floor(rand() * @num) where `id` = @gid;
			INSERT INTO `message`(`id`, `tiles`, `time`, `game_id`, `location` , `down`, `score`)
				VALUES (null, null, now(), @gid, (select `currentPlayer` - 6 FROM `game` where `id` = @gid), null, null);
			set errorCode = 0;
		end if;
	end if;
	END ;;";

	//proc getBoardSize
	$sql[] = "DROP procedure IF EXISTS `getBoardSize` ;";
	$sql[] = "CREATE DEFINER=`root`@`%` PROCEDURE `getBoardSize`(OUT `w` INT UNSIGNED, OUT `h` INT UNSIGNED)
	BEGIN
	SET w = (SELECT `value` FROM `constant_int` WHERE `group` = 'board_size' AND `key` = 'width');
	SET h = (SELECT `value` FROM `constant_int` WHERE `group` = 'board_size' AND `key` = 'height');
	END ;;";

	//proc getError
	$sql[] = "DROP procedure IF EXISTS `getError` ;";
	$sql[] = "CREATE DEFINER=`root`@`%` PROCEDURE `getError`(IN `errorC` INT(10) UNSIGNED, OUT `mess` varchar(100))
	BEGIN
	SET mess = (SELECT `message` FROM `error` WHERE `id` = errorC);
	END ;;";

	//proc getGame
	$sql[] = "DROP procedure IF EXISTS `getGame` ;";
	$sql[] = "CREATE DEFINER=`root`@`%` PROCEDURE `getGame`(IN lastGame int UNSIGNED, out ga_id int UNSIGNED, out gkey varchar(32))
	BEGIN
	set ga_id = (select `id` from `game` where `id` > lastGame and `hidden` = 0 order by `id` ASC limit 1);
	if ga_id is not null then
		set gkey = (select `key` from `game` where `id` = ga_id);
	end if;
	END ;;";

	//proc getGameStatus
	$sql[] = "DROP procedure IF EXISTS `getGameStatus` ;";
	$sql[] = "CREATE DEFINER=`root`@`%` PROCEDURE `getGameStatus`(IN `game` varchar(32), OUT `stat` varchar(10), OUT `currentP` tinyint UNSIGNED, OUT `p0` int UNSIGNED, OUT `p1` int UNSIGNED, OUT `p2` int UNSIGNED, Out `p3` int UNSIGNED, out `nam` varchar(50))
	begin
	set stat = (select `status` from `game` where `key` = game);
	set @g = (select `id` from `game` where `key` = game);
	call `_getPlayer`(0, @g, @p);
	set p0 = (select case when `passed` then -`score` else `score` end from `player` where `id` = @p);
	call `_getPlayer`(1, @g, @p);
	set p1 = (select case when `passed` then -`score` else `score` end from `player` where `id` = @p);
	call `_getPlayer`(2, @g, @p);
	set p2 = (select case when `passed` then -`score` else `score` end from `player` where `id` = @p);
	call `_getPlayer`(3, @g, @p);
	set p3 = (select case when `passed` then -`score` else `score` end from `player` where `id` = @p);
	set currentP = (select `currentPlayer` from `game` where `id` = @g);
	set nam = (select `name` from `game` where `id` = @g);
	END ;;";

	//proc getHand
	$sql[] = "DROP procedure IF EXISTS `getHand` ;";
	$sql[] = "CREATE DEFINER=`root`@`%` PROCEDURE `getHand`(IN `play` varchar(32), OUT `hand` varchar(10))
	BEGIN
	SET hand =
		(SELECT group_concat(tile.value, case when tile.dark = 1 then '1' else '0' end SEPARATOR '')
			FROM `tile`
			JOIN `tile_join` ON tile.id = `tile_id`
			JOIN `player` ON player.id = `player_id`
			WHERE player.`key` = play GROUP BY player.id);
	if hand is null then
		SET hand = '';
	end if;
	END ;;";

	//proc getLast
	$sql[] = "DROP procedure IF EXISTS `getLast` ;";
	$sql[] = "CREATE DEFINER=`root`@`%` PROCEDURE `getLast`(in gam varchar(32), out las int)
	BEGIN
	set las = (SELECT message.id FROM `message` JOIN `game` ON `game_id` = game.id WHERE `key` = gam ORDER BY message.id DESC LIMIT 1);
	END ;;";

	//proc getMessage
	$sql[] = "DROP procedure IF EXISTS `getMessage` ;";
	$sql[] = "CREATE DEFINER=`root`@`%` PROCEDURE `getMessage`(IN gam varchar(32), IN las int UNSIGNED, OUT mess_id INT UNSIGNED,
		OUT `ts` varchar(10), OUT `loc` tinyint, OUT `d` varchar(1), OUT `s` int)
	BEGIN
	set mess_id = (select message.id from `message` join `game` on game.id = `game_id` where `key` = gam and message.id > las order by message.id ASC limit 0,1);
	if mess_id is not null then
		set ts = (select `tiles` from `message` where `id` = mess_id);
		set loc = (select `location` from `message` where `id` = mess_id);
		set d = (select `down` from `message` where `id` = mess_id);
		set s = (select `score` from `message` where `id` = mess_id);
	end if;
	END ;;";

	//proc getName
	$sql[] = "DROP procedure IF EXISTS `getName` ;";
	$sql[] = "CREATE DEFINER=`root`@`%` PROCEDURE `getName`(IN `gam` varchar(32), IN `num` INT, OUT `nam` varchar(100))
	BEGIN
	call `_getPlayer`(num, (SELECT `id` from `game` where `key` = gam), @id);
	set nam = (SELECT `name` from `player` where `id` = @id);
	END ;;";

	//proc getNumOfPlayer
	$sql[] = "DROP procedure IF EXISTS `getNumOfPlayers` ;";
	$sql[] = "CREATE DEFINER=`root`@`%` PROCEDURE `getNumOfPlayers`(IN `gam` varchar(32), OUT `number` TINYINT UNSIGNED)
	BEGIN
	set number = (SELECT count(player.id) from `player` Join `game` on `game_id` = game.id where game.`key` = gam);
	END ;;";

	//proc getPlayerNum
	$sql[] = "DROP procedure IF EXISTS `getPlayerNum` ;";
	$sql[] = "CREATE DEFINER=`root`@`%` PROCEDURE `getPlayerNum`(in `play` varchar(32), out `n` tinyint UNSIGNED)
	BEGIN
	set n = null;
	set @g = (select `game_id` from `player` where `key` = play);
	set @i = 0;
	plays: LOOP
		call `_getPlayer`(@i, @g, @id);
		IF (select `key` from `player` where `id` = @id) = play THEN
			set n = @i;
			LEAVE plays;
		END IF;
		set @i= @i + 1;
		if @i = 4 then
			LEAVE plays;
		end if;
	END LOOP plays;
	END ;;";

	//proc getTileAt
	$sql[] = "DROP procedure IF EXISTS `getTileAt` ;";
	$sql[] = "CREATE DEFINER=`root`@`localhost` PROCEDURE `getTileAt`(IN `game` VARCHAR(32), IN `pos` TINYINT UNSIGNED, OUT `t` varchar(1), OUT `d` BIT(1))
	begin
	set @id = (select `id` from `game` where `key` = game);
	set t = (select `value` from `tile` join `tile_join` on tile.id = `tile_id` and `game_id` = @id and `location` = pos and `player_id` is null);
	set d = (select `dark` from `tile` join `tile_join` on tile.id = `tile_id` and `game_id` = @id and `location` = pos and `player_id` is null);
	end ;;";

	//proc drawTile
	$sql[] = "DROP procedure IF EXISTS `_drawTile` ;";
	$sql[] = "CREATE DEFINER=`root`@`%` PROCEDURE `_drawTile`(IN `gam` INT(11) UNSIGNED, OUT `tile` INT(11) UNSIGNED)
	BEGIN
	set tile = 0;
	if 0 < (SELECT count(tile.id)
		from `tile` where `id` not in
			(SELECT `id` from `tile_join` where `game_id` = gam or `player_id` in
				(select `id` from `player` where `game_id` = gam)
			)
	) then
		set tile =
			(SELECT tile.id
				from `tile` where `id` not in
					(SELECT `id` from `tile_join` where `game_id` = gam or `player_id` in
						(select `id` from `player` where `game_id` = gam)
					)
				order by RAND() LIMIT 1);
	end if;
	END ;;";

	//proc endTurn
	$sql[] = "DROP procedure IF EXISTS `_endTurn` ;";
	$sql[] = "CREATE DEFINER=`root`@`%` PROCEDURE `_endTurn`(IN `gam` INT(10) UNSIGNED)
	BEGIN
	-- check if every passed
	if 0 = (SELECT count(`id`) FROM `player` WHERE `game_id` = gam and `passed` = 0) then
		-- game is done
		update `player`
		JOIN (SELECT
				player_id id,
				sum(_getAgainst(`value`)) a
			from  `tile_join` tj
			join `tile` t on t.id = `tile_id`
			where tj.player_id is not null
			group by player_id) p
		on player.id = p.id and player.game_id = gam
		set `score` = `score` - a;

		update game set `status` = 'won' where `id` = gam;

		-- multiple rows are insert
		INSERT INTO `message`(`id`, `tiles`, `time`, `game_id`, `location` , `down`, `score`)
			SELECT null, null, now(), gam, -1, null, `score` from `player` where `game_id` = gam order by `id` asc;
	else
		-- next player's turn
		update `game` set `currentPlayer` = (`currentPlayer` + 1) % (SELECT count(`id`) FROM `player` WHERE `game_id` = gam) where `id` = gam;
	end if;
	END ;;";

	//proc fillUpHand
	$sql[] = "DROP procedure IF EXISTS `_fillUpHand` ;";
	$sql[] = "CREATE DEFINER=`root`@`%` PROCEDURE `_fillUpHand`(IN `player` INT(11) UNSIGNED)
	BEGIN
	set @gid = (Select `game_id` from `player` where `id` = player);
	addLoop: LOOP
		if 5 = (SELECT count(`id`) from `tile_join` where `player_id` = player) then
			leave addLoop;
		end if;
		CALL `_drawTile`(@gid, @t);
		if 0 = @t then
			leave addLoop;
		end if;
		INSERT into `tile_join` (id, tile_id, game_id, player_id) value (null, @t, null, player);
	END LOOP addLoop;
	END ;;";

	//proc getPlayer
	$sql[] = "DROP procedure IF EXISTS `_getPlayer` ;";
	$sql[] = "CREATE DEFINER=`root`@`localhost` PROCEDURE `_getPlayer`(IN `number` INT UNSIGNED, IN `game` INT UNSIGNED, OUT `id` INT UNSIGNED)
	begin
	set id = (SELECT d.id FROM (
			SELECT player.id,
				@rownum := @rownum + 1 AS rank
			FROM player,
				(SELECT @rownum := -1) r
			where `game_id` = game
		) d WHERE `rank` = number
		order by `id` asc);
	end ;;";

	//proc getScore
	$sql[] = "DROP procedure IF EXISTS `_getScore` ;";
	$sql[] = "CREATE DEFINER=`root`@`%` PROCEDURE `_getScore`(in `t0` int, in `t1` int, in `t2` int, in `t3` int, in `t4` int, out s int)
	BEGIN
	-- runs
	set s = 0;
	set @longest = 0;
	set @count = 0;

	CREATE TEMPORARY TABLE tileRow (`id` int not null AUTO_INCREMENT, `v` varchar(1), `d` bit not null, `used` bit, primary key (id));
	insert into `tileRow` (`id`,`v`, `d`, `used` )
		select null, `value`, `dark`, 0 from `tile` where `id` in (t0, t1, t2, t3, t4) order by _getOrder(`value`);
	set @i0 = 1;
	l0: loop
		set @i1 = @i0 + 1;
		l1: loop
			if (select count(`id`) from `tileRow`) < @i1 then
				leave l1;
			end if;

			-- two tiles;
			if 15 = (select sum(_getAgainst(`v`)) from `tileRow` where `id` in (@i0, @i1)) then
				-- fiften
				set s = s + 2;
				update `tileRow` set `used` = 1 where `id` in (@i0, @i1);
			end if;
			set @tmp  = (select `v` from `tileRow` where `id` = @i0);
			if @tmp = (select `v` from `tileRow` where `id` = @i1) then
				-- pair
				set s = s + 2;
				update `tileRow` set `used` = 1 where `id` in (@i0, @i1);
			end if;
			set @i2 = @i1 + 1;

			l2: loop
				if (select count(`id`) from `tileRow`) < @i2 then
					leave l2;
				end if;

				-- three tiles;

				if 15 = (select sum(_getAgainst(`v`)) from `tileRow` where `id` in (@i0, @i1, @i2)) then
					-- fiften
					set s = s + 2;
					update `tileRow` set `used` = 1 where `id` in (@i0, @i1, @i2);
				end if;

				set @row_num = 0;
				if 4 > @longest and 0 =
						(SELECT min(`o` - `r`) - max(`o` - `r`) from
							(SELECT  _getOrder(`v`) o,  @row_num := @row_num + 1 as r
							FROM `tileRow` where `id` in (@i0, @i1, @i2) order by `id`) a)  then
					set @longest = 3;
					set @count = @count + 1;
					update `tileRow` set `used` = 1 where `id` in (@i0, @i1, @i2);
				end if;
				set @i3 = @i2 + 1;
				l3: loop
					if (select count(`id`) from `tileRow`) < @i3 then
						leave l3;
					end if;

					-- four tiles;
					if 15 = (select sum(_getAgainst(`v`)) from `tileRow` where `id` in (@i0, @i1, @i2, @i3)) then
						-- fiften
						set s = s + 2;
						update `tileRow` set `used` = 1 where `id` in (@i0, @i1, @i2, @i3);
					end if;
					if 5 > @longest and 0 =
							(SELECT min(`o` - `r`) - max(`o` - `r`) from
								(SELECT  _getOrder(`v`) o,  @row_num := @row_num + 1 as r
							FROM `tileRow` where `id` in (@i0, @i1, @i2, @i3) order by `id`) a)  then
						update `tileRow` set `used` = 1 where `id` in (@i0, @i1, @i2, @i3);
						if 4 > @longest then
							set @longest = 4;
							set @count = 0;
						end if;
						set @count = @count + 1;
					end if;

					set @i4 = @i3 + 1;
					l4: loop
						if (select count(`id`) from `tileRow`) < @i4 then
							leave l4;
						end if;

						-- five tiles;
						if 5 = (select count(`id`) from `tileRow` Where `d` = 1) then
							-- all one colour
							set s = s + 10;
						end if;
						if 5 = (select count(`id`) from `tileRow` Where `d` = 0) then
							-- all one colour
							set s = s + 10;
						end if;
						if 15 = (select sum(_getAgainst(`v`)) from `tileRow`) then
							-- fiften
							set s = s + 2;
							update `tileRow` set `used` = 1 where `id` in (@i0, @i1, @i2, @i3, @i4);
						end if;

						if 0 = (SELECT min(`o` - `r`) - max(`o` - `r`) from
								(SELECT  _getOrder(`v`) o,  @row_num := @row_num + 1 as r
								FROM `tileRow` order by `id`) a)  then
							if 5 > @longest then
								set @longest = 5;
								set @count = 0;
							end if;

							set @count = @count + 1;
						end if;
						set @i4 = @i4 + 1;
					end loop l4;
					set @i3 = @i3 + 1;
				end loop l3;
				set @i2 = @i2 + 1;
			end loop l2;
			set @i1 = @i1 + 1;
		end loop l1;
		set @i0 = @i0 + 1;
		if (select count(`id`) from `tileRow`) < @i0 then
			leave l0;
		end if;
	end loop l0;
	set s = s + @longest * @count;
	if 0 < (select count(`id`) from `tileRow` where `used` = 0) then
		set s = 0;
	end if;
	drop table `tileRow`;
	END ;;";

	//proc fillTile
	$sql[] = "DROP procedure IF EXISTS `__fillTile` ;";
	$sql[] = "CREATE DEFINER=`root`@`%` PROCEDURE `__fillTile`()
	BEGIN
	truncate table `tile`;

	set @v = 'A234567891JQK';
	set @c = 5;
	set @i = 0;

	l: loop
		INSERT INTO `tile`(`id`, `value`, `dark`)
			VALUES (null, substring(@v, @i % CHAR_LENGTH(@v) + 1), @i % 2);

		set @i = @i + 1;
		if @i = CHAR_LENGTH(@v) * @c * 2 then
			leave l;
		end if;
	end loop l;
	END ;;";

	$sql[] = "call __fillTile();";

	foreach($sql as $s){
		mysqli_query($kng_con, $s);
		if(mysqli_error($kng_con)){
			http_response_code(500);
			die("Failed to initDatabase: ".mysqli_error($kng_con)."<br />$s");
		}
	}
}
