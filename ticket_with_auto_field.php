<?php
/*
 * Базовый класс для карточки с автоматической работой с полями
 *
 */

// подгрузить необходимые функций и классов
include_once $GLOBALS['path_model'] .'/system/common.php';
include_once $GLOBALS['path_model'] .'/system/log.php';
include_once $GLOBALS['path_model'] .'/system/manual.php';

// описание класса
class TicketWithAutoField {

	//------------------------------------------------------------------------------------------------------------------------------------------------------
	// свойства

	// структура описывающая все поля данной карточки в БД
	// каждый элемент массива содержит ключ, как название поля, и структуру:
	//	'sql'	- наименование поля в БД
	//	'value'	- значение поля (при формировании структуры задаётся значение по умолчанию)
	//	'text'	- читабельное название поля
	//	'type'	- тип поля (см. метод getFieldValueReadableFormat)
	protected $field = array();
	protected $field_new = array(); // копия структуры $field для сохранения

	// ключ от структуры $field для первичного ключа
	protected $field_system_key = null;

	// ключ от структуры $field для даты изменения записи (автоматически сохраняется текущ. датой при любом сохранении)
	protected $field_system_update_date = null;

	// имя таблицы в БД
	protected $sql_table_name = null;

	// логирование (id для messages.type) (при значении NULL логирование не ведётся)
	protected $log_type_id = null;

	//------------------------------------------------------------------------------------------------------------------------------------------------------
	// конструктор
	public function __construct( $id = null ) {

		// автоматический запуск загрузки всех полей при получении id
		if( $id ) {
			$this->field[ $this->field_system_key ][ 'value' ] = (int) $id;
			$this->loadDataFromDB();
		} else {
			$this->resetSetData();
		}
	}

	//------------------------------------------------------------------------------------------------------------------------------------------------------
	// методы получения данных

	// Значение поля
	public function getFieldValue( $field_key ) {
		return $this->field[ $field_key ][ 'value' ];
	}

	// Название поля
	public function getFieldText( $field_key ) {
		return $this->field[ $field_key ][ 'text' ];
	}

	// Значение поля в читабельном формате
	public function getFieldValueReadableFormat( $field_key, $new_data_flag = false ) {

		$result = '';
		if( $new_data_flag ) {
			$value  = $this->field_new[ $field_key ][ 'value' ];
			$type   = $this->field_new[ $field_key ][ 'type' ];
			if( isset( $this->field_new[ $field_key ][ 'readformat' ] ) ) {
				$result = $this->field_new[ $field_key ][ 'readformat' ];
			}
		} else {
			$value  = $this->field[ $field_key ][ 'value' ];
			$type   = $this->field[ $field_key ][ 'type' ];
			if( isset( $this->field[ $field_key ][ 'readformat' ] ) ) {
				$result = $this->field[ $field_key ][ 'readformat' ];
			}
		}

		// если результат не закеширован
		if( !$result ) {

			// числовое поле
			if( $type == 'int' && $value !== null ) {

				$result = (int) $value;

			// текстовое поле
			} else if( $type == 'text' && $value ) {

				$result = str_replace( "\n", '<br>', $value );

			// поле даты (xxxx-xx-xx)
			} else if( $type == 'date' && $value && $value != '0000-00-00' ) {

				$result = substr($value,8,2) .'.'. substr($value,5,2) .'.'. substr($value,0,4);

			// да/нет
			} else if( $type == 'YN' && $value ) {

				$result = ( $value == 'Y' ) ? 'да' : ( ( $value == 'N' ) ? 'нет' : '' );

			// системный справочник
			} else if( $type == 'manual' && $value ) {

				list( $result ) = Manual::getItemById( $value );
			}

			// сохранить полученое значение
			if( $new_data_flag ) {
				$this->field_new[ $field_key ][ 'readformat' ] = $result;
			} else {
				$this->field[ $field_key ][ 'readformat' ] = $result;
			}
		}

		return $result;
	}

	//------------------------------------------------------------------------------------------------------------------------------------------------------
	// методы изменения данных

	// Изменить значение поля
	public function setFieldValue( $field_key, $field_value ) {
		$this->field_new[ $field_key ][ 'value' ] = $field_value;
		return $this;
	}

	// Изменить значение поля не для сохранения, а в базовой структуре
	public function setFieldValueWithoutSave( $field_key, $field_value ) {
		$this->field[ $field_key ][ 'value' ] = $field_value;
		return $this;
	}

	// сброс всех несохранённых изменений в классе
	private function resetSetData() {
		$this->field_new = $this->field;
	}

	// подтверждение всех сохранённых изменений в классе
	private function confirmSetData() {
		$this->field = $this->field_new;
	}

	//------------------------------------------------------------------------------------------------------------------------------------------------------
	// загрузка данных из БД
	private function loadDataFromDB() {

		// сформировать SQL запрос
		$sql_field = '';
		foreach( $this->field as $value ) {
			$sql_field .= ', '. $value['sql'];
		}
		$sql =
			'SELECT '. substr($sql_field,2)
			.' FROM '. $this->sql_table_name
			.' WHERE '. $this->field[ $this->field_system_key ][ 'sql' ] .'='. $this->field[ $this->field_system_key ][ 'value' ];

		// заполнить значения полей
		$tmp_array = DataBase::GetValues( $sql );
		if( count($tmp_array) ) {
			foreach( $this->field as $key => $value ) {
				$this->field[ $key ][ 'value' ] = array_shift( $tmp_array );
			}
		} else {
			$this->field[ $this->field_system_key ][ 'value' ] = null;
		}

		// заполнить структуру для сохранения полей
		$this->resetSetData();

		// вернуть результат запроса
		return $this->field[ $this->field_system_key ];
	}

	//------------------------------------------------------------------------------------------------------------------------------------------------------
	// запись данных в БД

	// базовый метод записи
	public function saveDataToDB() {

		// признак новой записи
		$create_ticket_flag = ( $this->field_new[ $this->field_system_key ]['value'] ) ? false : true;

		// действия до сохранения
		$this->saveDataToDBBeforeSave( $create_ticket_flag );

		// запись в БД - создать новую запись
		if( $create_ticket_flag ) {

			$sql_result = $this->saveNewDataToDB();

		// запись в БД -  редактировать существующую запись
		} else {

			$sql_result = $this->saveUpdateDataToDB();
		}

		// продолжить если запись в БД прошла успешно
		if( $sql_result ) {

			// действия после успешного сохранения
			$this->saveDataToDBAfterSave( $create_ticket_flag );

			// логирование
			if( $this->log_type_id ) {
				$this->addLog( $create_ticket_flag );
			}

			// подтверждение всех сохранённых изменений в классе
			$this->confirmSetData();
		}

		// вернуть результат SQL запроса
		return $sql_result;
	}

	// запись данных в БД - создать новую запись
	private function saveNewDataToDB() {

		// сформировать SQL запрос
		$sql_field = '';
		foreach( $this->field_new as $value ) {
			$sql_field .= ', '. $value['sql'] .' = '. ( ( $value['value'] === null ) ? 'NULL' : '\'' . $value['value'] . '\'' );
		}
		$sql = 'INSERT INTO '. $this->sql_table_name .' SET '. substr($sql_field,2);

		// выполнить SQL запрос
		$sql_result = DataBase::Query( $sql );
		$this->field_new[ $this->field_system_key ]['value'] = DataBase::GetInsertId();

		// вернуть результат запроса
		return $sql_result;
	}

	// запись данных в БД - редактировать существующую запись
	private function saveUpdateDataToDB() {

		// сформировать SQL запрос (не полный, только поля, которые будут изменены)
		$sql_field = '';
		foreach( $this->field_new as $key => $value ) {
			if( $this->field[ $key ][ 'value' ] != $value['value'] ) {
				$sql_field .= ', '. $value['sql'] .' = '. ( ( $value['value'] === null ) ? 'NULL' : '\'' . $value['value'] . '\'' );
			}
		}

		// если были изменения
		if( $sql_field ) {

			// если есть автоматическое обновление даты
			if( $this->field_system_update_date ) {
				$this->field_new[ $this->field_system_update_date ][ 'value' ] = date("Y-m-d H:i:s");
				$sql_field .= ', '. $this->field_new[ $this->field_system_update_date ][ 'sql' ] .' = \'' . $this->field_new[ $this->field_system_update_date ][ 'value' ] . '\'';
			}

			// закончить формирование SQL запроса
			$sql = 'UPDATE '. $this->sql_table_name .' SET '. substr($sql_field,2)
			.' WHERE '. $this->field[ $this->field_system_key ][ 'sql' ] .'='. $this->field[ $this->field_system_key ][ 'value' ];

			// выполнить SQL запрос и вернуть результат
			$sql_result = DataBase::Query( $sql );
			return $sql_result;

		// если изменений не было
		} else {
			return false;
		}
	}

	// при необходимости методы ниже можно переопределить
	// $create_ticket_flag - это признак новой записи (true - новая запись, false - редактирование старой записи)
	protected function saveDataToDBBeforeSave( $create_ticket_flag ) { }	// действия до сохранения
	protected function saveDataToDBAfterSave( $create_ticket_flag ) { }	// действия после успешного сохранения

	//------------------------------------------------------------------------------------------------------------------------------------------------------
	// логирование
	private function addLog( $create_ticket_flag ) {

		$tmp_message_title = $tmp_message_text = '';

		// при создании
		if( $create_ticket_flag ) {

			// сформировать уведомление
			$tmp_message_title = 'Пользователь '. $GLOBALS['user_name'] .' ('. $GLOBALS['user_dealer_name'] .') создал карточку №'. $this->field_new[ $this->field_system_key ][ 'value' ];
			$tmp_message_text = '<table border="1"><tr align="center"><td>Наименование поля</td><td>Значение</td></tr>'. "\n";
			foreach( $this->field_new as $key => $value ) {
				$tmp_message_text .=
					'<tr><td>'. $this->field[ $key ][ 'text' ]
					.'</td><td>'. $this->getFieldValueReadableFormat( $key, true ) ."</td></tr>\n";
			}
			$tmp_message_text .= '</table>';

		// при редактировании
		} else {

			// сформировать уведомление
			foreach( $this->field_new as $key => $value ) {
				if( $this->field[ $key ][ 'value' ] != $value['value'] && $key != $this->field_system_update_date ) {
					$tmp_message_text .=
						'<tr><td>'. $this->field[ $key ][ 'text' ]
						.'</td><td>'. $this->getFieldValueReadableFormat( $key, true )
						.'</td><td>'. $this->getFieldValueReadableFormat( $key ) ."</td></tr>\n";
				}
			}
			if( $tmp_message_text ) {
				$tmp_message_title = 'Пользователь '. $GLOBALS['user_name'] .' ('. $GLOBALS['user_dealer_name'] .') изменил карточку №'. $this->field_new[ $this->field_system_key ][ 'value' ];
				$tmp_message_text = '<table border="1"><tr align="center"><td>Наименование поля</td><td>Новое значение</td><td>Старое значение</td></tr>'. "\n". $tmp_message_text .'</table>';
			}
		}

		// сохранение уведомления в БД
		if( $tmp_message_title ) {
			$tmp_log = new SystemLog();
			$tmp_log->setTypeId( $this->log_type_id )
				->setItemId( $this->field_new[ $this->field_system_key ][ 'value' ] )
				->setTitle( $tmp_message_title )
				->setText( $tmp_message_text )
				->saveDataToDB();
		}
	}
}

