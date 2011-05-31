<?php /** 
 * inPHP, declarative reflective performance oriented MVC framework with built-in ORM
 * Copyright (c) 2011 Jan Groothuijse, inLogic (inlogic.nl)
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * See <http://www.gnu.org/licenses/>.
 */
	namespace Hotel;
	// Provingground application
	\inPHP\Conf::set('DB.default', new \inPHP\ORM\DB\DB('P:localhost', 'user', 'passwd', 'schema'));
	\inPHP\Conf::set('App.home', 'Hotel\HotelHome');
	\inPHP\Conf::set('App.controlSpace', 'Hotel\\');
	\inPHP\Conf::set('App.modelSpace', 'Hotel\\');
	
	use inPHP\ORM\Model, inPHP\ORM\DQ, inPHP\ORM\MF, inPHP\ORM\DAO, inPHP\ORM\Enum;
	use inPHP\View\ViewGenerator;

	class Hotel extends Model {
		/** @inPHP\ORM\PrimaryKey; @inPHP\ORM\String; */
		protected $name;
		/** @inPHP\ORM\String; */
		protected $email;
		/** @inPHP\ORM\String; */
		protected $address;
		/** @inPHP\ORM\Many('Hotel\HotelRoom', 'hotel'); */
		protected $rooms;
	}
	
	class RoomType extends Enum { 
		const SUITE = 'suite', ROOM = 'room';
	}
	
	final class HotelRoom extends Model {
		/** @inPHP\ORM\PrimaryKey; @Hotel\Hotel; */
		protected $hotel;
		/** @inPHP\ORM\PrimaryKey; @inPHP\ORM\Integer; */
		protected $number;
		/** @inPHP\ORM\Integer; */
		protected $maxOccupants;
		/** @Hotel\RoomType; */
		protected $type;
		/** @inPHP\ORM\Many('Hotel\Reservation', 'hotelRoom'); */
		protected $reservations;
	}
	
	final class Reservation extends Model {
		/** @Hotel\HotelRoom; @inPHP\ORM\PrimaryKey; */
		protected $hotelRoom;
		/** @inPHP\ORM\DateAndTime; @inPHP\ORM\PrimaryKey; */
		protected $date;
		/** @inPHP\ORM\Integer; number of days */
		protected $duration;
	}
	
	class HotelTemplate implements \inPHP\View\IOutputTemplate {
			/** Can send the start of the document, and install output buffers */
			function head() { ?><html><head>
				<link rel="stylesheet" type="text/css" href="http://hotel.local/style.css" /><?php }
			/** Sends the block up to the content block, then releases the buffer */
			function misc($misc) { ?>
				<title><?php echo $misc; ?></title></head>
				<body><h1><?php echo $misc;
				?></h1><?php flush(); // yes, flush is mostly ignored, but still..
			}
			/** Send the end of the document */
			function tail() { ?></body></html><?php }
	}
	
	class HotelHome implements \inPHP\Control\IController {
		protected $template, $outputHandler, $hotel, $hotelView, $hotelRoom, $hotelRoomView,
					$reservationView;
		const HOTELQUERY = 'HotelQuery', HOTELROOMQUERY = 'HotelRoomQuery';
		
		function __construct() {
			$this->template = new HotelTemplate();
			$this->outputHandler = new \inPHP\Control\OutputHandler();
			$this->hotel = DAO::Hotel();
			$this->hotel->createNamedQuery(self::HOTELQUERY, DQ::select(MF::email(), MF::address())
													->where(MF::name()->filter('=', '%1$s')));
			$this->hotelView = ViewGenerator::Hotel();
			$this->hotelRoom = DAO::HotelRoom();
			$this->hotelRoom->createNamedQuery(self::HOTELROOMQUERY, 
														DQ::select(MF::maxOccupants(), MF::type()));
			$this->hotelRoomView = ViewGenerator::HotelRoom();
			$this->reservationView = ViewGenerator::Reservation();
		}
		
		function main($args = array(), $get, $post, $files) {
			$this->template->misc('Hotel Index');
			$hotel = $this->hotel;
			$hotelView = $this->hotelView;
			$hotelRoom = $this->hotelRoom;
			$hotelRoomView = $this->hotelRoomView;
			$reservationView = $this->reservationView;
			//\inPHP\View\outputCached('HotelHome', 'Hotel', function () use ($hotel, $hotelView, 
			//													$hotelRoom, $hotelRoomView) {
				call_user_func($hotel->namedQuery(HotelHome::HOTELQUERY, array('BlueHotel 1')),
								function ($h) use ($hotelView) { $hotelView->display($h); });				
				$fetcher = $hotel->join('rooms', DQ::select()->join(new \inPHP\ORM\LeftJoin(DQ::select(MF::duration()),
										array(MF::hotelRoom()->filter('=', array(MF::hotel(), MF::number()))),
										'reservations', 'Hotel\Reservation')))->query(DQ::select());
				$fetcher(function ($h, $r) use ($hotelView, $hotelRoomView, $reservationView) { //print_r($r); print_r($h);
					$hotelView->display($h,array('rooms' => function($null) {$null=null;}));
					$rooms = $h->rooms();
					$rooms(function ($room) use ($hotelRoomView, $reservationView) { 
							$hotelRoomView->display($room, array('hotel' => function($null) {$null=null;},
															'reservations' => function ($n) { $n=null;}));
							call_user_func($room->reservations(), 
								function ($r) use ($reservationView) { $reservationView->display($r); });							
						});
					});
				$blueHotelOne = new Hotel(); $blueHotelOne->name('BlueHotel 1');
				$roomOneFetcher = $hotelRoom->byPK(array($blueHotelOne, '1'));
				$roomOneFetcher(function ($model) { print_r($model); });
				$fetcher = $hotelRoom->namedQuery(HotelHome::HOTELROOMQUERY);
				$properties = array('hotel' => function ($hotel) { $hotel = $hotel->name(); });
				$fetcher(function ($hr) use ($hotelRoomView, &$properties) 
								{ $hotelRoomView->display($hr, &$properties);} );
			//});				
		}
		function outputHandler() {	return $this->outputHandler;	}
		function template() {	return $this->template;	}
		function extension() { return 'html'; }
	}

?>