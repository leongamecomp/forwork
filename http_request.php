<?php
/*
 * Базовый класс для HTTP запросов к серверу СЛ
 *
 */

// подгрузить необходимые классы
include_once $GLOBALS['path_model'] .'/system/common.php';

// описание класса
class LicenseServerHttpRequest {

	//------------------------------------------------------------------------------------------------------------------------------------------------------
	// свойства

	// базовые данные
	private $url			= null;			// url-адресс						(string)
	private $request		= '';			// тело HTTP запроса					(string)
	private $request_array		= '';			// тело HTTP запроса, которое будет обёрнуто в массив	(string)
	private $response		= null;			// ответ на HTTP запрос					(string)
	protected $request_log		= '';			// лог HTTP запроса					(string)
	protected $request_array_log	= '';			// лог HTTP запроса, который будет обёрнуто в массив	(string)
	protected $response_log		= '';			// лог HTTP ответа					(string)

	// данные для расшифровки HTTP ответа
	private $response_index		= 0;			// метка на позицию в строке HTTP ответа		(int)

	//------------------------------------------------------------------------------------------------------------------------------------------------------
	// формирование тела HTTP запроса

	// очистить тело HTTP запроса
	protected function clearRequest() {

		$this->request = '';
		$this->request_array = '';
		$this->request_log = '';
		$this->request_array_log = '';
		$this->response_log = '';
		$this->response_index = 0;
		return $this;
	}

	// очистить ответ на HTTP запрос (используется для очистки памяти)
	protected function clearResponse() {

		$this->response = null;
		return $this;
	}

	// добавить поле типа 'bool' в тело HTTP запроса
	protected function addBoolToRequest( $value, $name = null ) {

		$this->request .= ( $value ) ? chr( 1 ) : chr( 0 );
		if( $name ) {
			$this->request_log .= $name .' [bool] = '. ( ( $value ) ? 'TRUE' : 'FALSE' ) ."\n";
		}
		return $this;
	}
	protected function addBoolToRequestArray( $value, $name = null ) {

		$this->request_array .= ( $value ) ? chr( 1 ) : chr( 0 );
		if( $name ) {
			$this->request_array_log .= $name .' [bool] = '. ( ( $value ) ? 'TRUE' : 'FALSE' ) ."\n";
		}
		return $this;
	}

	// добавить поле типа 'byte' в тело HTTP запроса
	protected function addByteToRequest( $value, $name = null ) {

		$this->request .= chr( $value );
		if( $name ) {
			$this->request_log .= $name .' [byte] = '. $value ."\n";
		}
		return $this;
	}
	protected function addByteToRequestArray( $value, $name = null ) {

		$this->request_array .= chr( $value );
		if( $name ) {
			$this->request_array_log .= $name .' [byte] = '. $value ."\n";
		}
		return $this;
	}

	// добавить поле типа 'int' в тело HTTP запроса
	protected function addIntToRequest( $value, $name = null ) {

		$this->request .=
			  chr(   $value         & 0xff )
			. chr( ( $value >> 8  ) & 0xff )
			. chr( ( $value >> 16 ) & 0xff )
			. chr( ( $value >> 24 ) & 0xff );
		if( $name ) {
			$this->request_log .= $name .' [int] = '. $value ."\n";
		}
		return $this;
	}
	protected function addIntToRequestArray( $value, $name = null ) {

		$this->request_array .=
			  chr(   $value         & 0xff )
			. chr( ( $value >> 8  ) & 0xff )
			. chr( ( $value >> 16 ) & 0xff )
			. chr( ( $value >> 24 ) & 0xff );
		if( $name ) {
			$this->request_array_log .= $name .' [int] = '. $value ."\n";
		}
		return $this;
	}

	// добавить поле типа 'guid' в тело HTTP запроса
	protected function addGuidToRequest( $value, $name = null ) {

		$this->request .= chr( 1 ) . chr( 36 ) . $value;
		if( $name ) {
			$this->request_log .= $name .' [guid] = '. $value ."\n";
		}
		return $this;
	}
	protected function addGuidToRequestArray( $value, $name = null ) {

		$this->request_array .= chr( 1 ) . chr( 36 ) . $value;
		if( $name ) {
			$this->request_array_log .= $name .' [guid] = '. $value ."\n";
		}
		return $this;
	}

	// добавить поле типа 'string' в тело HTTP запроса
	protected function addStringToRequest( $value, $name = null ) {

		$value_len = strlen( $value );
		if( $value_len < 256 ) {

			$this->request .= chr( 1 ) . chr( $value_len );

		} else if( $value_len < 65536 ) {

			$this->request .= chr( 2 ) . chr( $value_len & 0xff ) . chr( ( $value_len >> 8 ) & 0xff );

		} else if( $value_len < 16777216 ) {

			$this->request .= chr( 3 ) . chr( $value_len & 0xff ) . chr( ( $value_len >> 8 ) & 0xff ) . chr( ( $value_len >> 16 ) & 0xff );

		} else {

 			$this->request .= chr( 4 ) . chr( $value_len & 0xff ) . chr( ( $value_len >> 8 ) & 0xff ) . chr( ( $value_len >> 16 ) & 0xff ) . chr( ( $value_len >> 24 ) & 0xff );
		}
		$this->request .= $value;
		if( $name ) {
			$this->request_log .= $name .' [string] = '. $value ."\n";
		}
		return $this;
	}
	protected function addStringToRequestArray( $value, $name = null ) {

		$value_len = strlen( $value );
		if( $value_len < 256 ) {

			$this->request_array .= chr( 1 ) . chr( $value_len );

		} else if( $value_len < 65536 ) {

			$this->request_array .= chr( 2 ) . chr( $value_len & 0xff ) . chr( ( $value_len >> 8 ) & 0xff );

		} else if( $value_len < 16777216 ) {

			$this->request_array .= chr( 3 ) . chr( $value_len & 0xff ) . chr( ( $value_len >> 8 ) & 0xff ) . chr( ( $value_len >> 16 ) & 0xff );

		} else {

 			$this->request_array .= chr( 4 ) . chr( $value_len & 0xff ) . chr( ( $value_len >> 8 ) & 0xff ) . chr( ( $value_len >> 16 ) & 0xff ) . chr( ( $value_len >> 24 ) & 0xff );
		}
		$this->request_array .= $value;
		if( $name ) {
			$this->request_array_log .= $name .' [string] = '. $value ."\n";
		}
		return $this;
	}

	// обернуть все данные HTTP запроса в массив с заданным числом элементов
	protected function addArrayDataToRequest( $value, $name = null ) {

		if( $value < 256 ) {

			$this->request .= chr( 1 ) . chr( 1 ) . chr( $value ) . $this->request_array;

		} else if( $value < 65536 ) {

			$this->request .= chr( 1 ) . chr( 2 ) . chr( $value & 0xff ) . chr( ( $value >> 8 ) & 0xff ) . $this->request_array;

		} else if( $value < 16777216 ) {

			$this->request .= chr( 1 ) . chr( 3 ) . chr( $value & 0xff ) . chr( ( $value >> 8 ) & 0xff ) . chr( ( $value >> 16 ) & 0xff ) . $this->request_array;

		} else {

 			$this->request .= chr( 1 ) . chr( 4 ) . chr( $value & 0xff ) . chr( ( $value >> 8 ) & 0xff ) . chr( ( $value >> 16 ) & 0xff ) . chr( ( $value >> 24 ) & 0xff ) . $this->request_array;
		}
		if( $name ) {
			$this->request_log .= $name .' [array] ( count = '. $value ." ) = [\n". $this->request_array_log ."]\n";
		}
		$this->request_array = '';
		$this->request_array_log = '';
		return $this;
	}

	//------------------------------------------------------------------------------------------------------------------------------------------------------
	// задать url-адресс без указания протокола, имени сервера и порта
	protected function setShortUrl( $value ) {

		$this->url = 'https://xxxxxxxxxxxxxx.xxxxxxxxxxxxx.xxxxxxxxx:xxxxxxxxx/'. $value;
		return $this;
	}

	//------------------------------------------------------------------------------------------------------------------------------------------------------
	// вернуть тело HTTP запроса
	public function getRequestBody() {

		return '"'. base64_encode( $this->request ) .'"';
	}

	//------------------------------------------------------------------------------------------------------------------------------------------------------
	// выполнить HTTP запрос
	protected function request() {

		// проверка на разрешение запроса
		if( $GLOBALS['mainflag_licensing'] == false ) {

			$this->response_log .= "Запросы к серверу СЛ запрещены настройками системы\n";
			return false;
		}

		// задать хедеры запроса
		$headers = array(
			'Accept: application/json,text/html,application/xhtml+xml,application/xml;q=0.9,*;q=0.8',
			'Accept-Language: ru,en-us;q=0.7,en;q=0.3',
			'Accept-Encoding: deflate',
			'Accept-Charset: utf-8,windows-1251;q=0.7,*;q=0.7'
		);

		// конвертировать тело запроса в Base64 и обернуть в Json
		$this->request = '"'. base64_encode( $this->request ) .'"';

		// выполнить запрос
		$request = curl_init();
		curl_setopt( $request, CURLOPT_URL, $this->url );
		curl_setopt( $request, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.0; MyIE2; .NET CLR 1.1.4322)' );
		curl_setopt( $request, CURLOPT_HEADER, true );
		curl_setopt( $request, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $request, CURLOPT_HTTPHEADER, $headers );
		curl_setopt( $request, CURLOPT_POST, true );
		curl_setopt( $request, CURLOPT_POSTFIELDS, $this->request );
		curl_setopt( $request, CURLOPT_TIMEOUT, 60 );
		curl_setopt( $request, CURLOPT_SSL_VERIFYPEER, 0 );
		$out = curl_exec( $request );
		curl_close( $request );

		// запрос выполнился
		if( $out ) {

			// получить первую строку ответа
			$head = substr( $out, 0, strpos( $out, "\r\n" ) );

			// заголовок верный
			if( $head == 'HTTP/1.1 200 OK' ) {

				// расшифровать тело ответа
				$this->response = base64_decode( $this->response );

				// записать результат
				$this->response_log .= "Запрос прошёл успешно\n";
				return true;

			// заголовок с ошибкой
			} else {

				$this->response_log .= "Запрос прошёл, но сервер вернул ошибку: $head\n";
				return false;
			}

		// запрос не выполнился
		} else {
		
			$this->response_log .= "Ошибка подключения к серверу СЛ\n";
			return false;
		}
	}

	//------------------------------------------------------------------------------------------------------------------------------------------------------
	// расшифровка тела HTTP ответа
	// все методы автоматически увеличивают метку на позицию в строке HTTP ответа на велечину считанных байт

	// расшифровать поле типа 'bool'
	protected function getBoolFromResponse() {

		return (bool) ord( $this->response[ $this->response_index++ ] );
	}

	// расшифровать поле типа 'byte'
	protected function getByteFromResponse() {

		return ord( $this->response[ $this->response_index++ ] );
	}

	// расшифровать поле типа 'guid'
	protected function getGuidFromResponse() {

		$this->response_index += 38;
		return substr( $this->response, $this->response_index - 36, 36 );
	}

	// расшифровать поле типа 'string'
	protected function getStringFromResponse() {

		$size_length = ord( $this->response[ $this->response_index ] );
		if( $size_length == 1 ) {

			$string_length = ord( $this->response[ $this->response_index + 1 ] );
			$this->response_index += 2 + $string_length;

		} else if( $size_length == 2 ) {

			$string_length = ord( $this->response[ $this->response_index + 1 ] ) + ( ord( $this->response[ $this->response_index + 2 ] ) << 8 );
			$this->response_index += 3 + $string_length;

		} else if( $size_length == 3 ) {

			$string_length =
				    ord( $this->response[ $this->response_index + 1 ] )
				+ ( ord( $this->response[ $this->response_index + 2 ] ) << 8 )
				+ ( ord( $this->response[ $this->response_index + 3 ] ) << 16 );
			$this->response_index += 4 + $string_length;

		} else {

			$string_length =
				    ord( $this->response[ $this->response_index + 1 ] )
				+ ( ord( $this->response[ $this->response_index + 2 ] ) << 8 )
				+ ( ord( $this->response[ $this->response_index + 3 ] ) << 16 )
				+ ( ord( $this->response[ $this->response_index + 4 ] ) << 24 );
			$this->response_index += 5 + $string_length;
		}
		return substr( $this->response, $this->response_index - $string_length, $string_length );
	}

	// расшифровать поле типа 'length'
	protected function getLengthFromResponse() {

		$size_length = ord( $this->response[ $this->response_index ] );
		if( $size_length == 1 ) {

			$length = ord( $this->response[ $this->response_index + 1 ] );
			$this->response_index += 2;

		} else if( $size_length == 2 ) {

			$length = ord( $this->response[ $this->response_index + 1 ] ) + ( ord( $this->response[ $this->response_index + 2 ] ) << 8 );
			$this->response_index += 3;

		} else if( $size_length == 3 ) {

			$length =
				    ord( $this->response[ $this->response_index + 1 ] )
				+ ( ord( $this->response[ $this->response_index + 2 ] ) << 8 )
				+ ( ord( $this->response[ $this->response_index + 3 ] ) << 16 );
			$this->response_index += 4;

		} else {

			$length =
				    ord( $this->response[ $this->response_index + 1 ] )
				+ ( ord( $this->response[ $this->response_index + 2 ] ) << 8 )
				+ ( ord( $this->response[ $this->response_index + 3 ] ) << 16 )
				+ ( ord( $this->response[ $this->response_index + 4 ] ) << 24 );
			$this->response_index += 5;
		}
		return $length;
	}
}
