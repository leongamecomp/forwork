<?php
/*
 * Формирование SQL-запроса для класса Ticket
 *
 */

// подгрузить необходимые классы
include_once $GLOBALS['path_model'] .'/system/common.php';
include_once $GLOBALS['path_model'] .'/common/ticket_list.php';

// описание класса
class TicketListSql extends TicketList {

	//------------------------------------------------------------------------------------------------------------------------------------------------------
	// свойства

	// название основной таблицы
	protected	$sql_main_table_name = 'table_name';

	// массив соответствий для сортировки
	protected	$sql_order_templates = array();		// array[ 'field' => 'sql_string' ]

	// Условия выборки
	protected	$sql_where = '';			// Итоговые условия для SQL-запроса				(str)

	// Присоединения таблиц
	protected	$sql_join = '';				// Итоговые присоединения таблиц для SQL-запроса		(str)

	// Группировка
	protected	$sql_group = '';			// Итоговая группировка для SQL-запроса				(str)

	// Сортировка
	private		$sql_order = '';			// Итоговая сортировка для SQL-запроса				(str)
	private		$order = array();			// Поле и порядок сортировки					(array[ 'field' => const str, 'invert' => bool (true- ASC, false- DESC) ])

	// Ограничения по количеству
	private		$sql_limit = '';			// Итоговые ограничения по количеству для SQL-запроса		(str)
	private		$limit_start = false;			// С какой позиции начать выборку				(int)
	private		$limit_count = false;			// Сколько строк выбрать					(int)

	// для подсчёта страниц и общего количества
	private		$count = null;				// Общее колличество записей в БД				(int)
	private		$page = 1;				// Номер текущей страницы					(int)
	private		$page_limit = 50;			// Ограничение записей на одну страницу				(int)

	//------------------------------------------------------------------------------------------------------------------------------------------------------
	// методы получения данных

	public function getCount()	{ return $this->count; }
	public function getPage()	{ return $this->page; }

	//------------------------------------------------------------------------------------------------------------------------------------------------------
	// методы изменения свойств

	public function setOrder( $field, $invert ) {
		$this->order[] = array(
			'field' => $field,
			'invert' => $invert,
		);
		return $this;
	}

	public function setPage( $value ) {
		$this->page = $value;
		return $this;
	}

	//------------------------------------------------------------------------------------------------------------------------------------------------------
	// сформировать SQL-запрос

	// сформировать отдельные части SQL-запроса
	protected function makeSql() {

		// очистить переменные
		$this->sql_where = '';
		$this->sql_join  = '';
		$this->sql_group = '';
		$this->sql_order = '';
		$this->sql_limit = '';

		// сформировать условия для SQL-запроса
		$this->makeSqlWhere();

		// сформировать группировку для SQL-запроса
		$this->makeSqlGroup();

		// сформировать сортировку для SQL-запроса
		$this->makeSqlOrder();

		// сформировать присоединения таблиц для SQL-запроса
		$this->makeSqlJoin();

		// сформировать ограничения по количеству для SQL-запроса
		$this->makeSqlLimit();

		// дописать необходимые операторы
		if( $this->sql_where ) {
			$this->sql_where = ' WHERE '. substr( $this->sql_where, 4 );
		}
		if( $this->sql_group ) {
			$this->sql_group = ' GROUP BY '. substr( $this->sql_group, 1 );
		}
		if( $this->sql_order ) {
			$this->sql_order = ' ORDER BY '. substr( $this->sql_order, 1 );
		}
	}

	// получить SQL-запрос (без полей)
	protected function getSql() {

		return ' FROM '. $this->sql_main_table_name .' t1 '. $this->sql_join . $this->sql_where . $this->sql_group . $this->sql_order . $this->sql_limit;
	}

	// получить урезанный SQL-запрос, только from и where (без полей)
	protected function getSqlFromWhereOnly() {

		return ' FROM '. $this->sql_main_table_name .' t1 '. $this->sql_join . $this->sql_where;
	}

	// шаблоны метадов, которые можно переопределить
	protected function makeSqlWhere() { }
	protected function makeSqlJoin()  { }
	protected function makeSqlGroup() { }

	// сформировать сортировку для SQL-запроса
	private function makeSqlOrder() {
		foreach( $this->order as $value ) {
			if( isset( $this->sql_order_templates[ $value['field'] ] ) ) {
				$this->sql_order .= ', '. $this->sql_order_templates[ $value['field'] ] . ( $value['invert'] ? ' ASC' : ' DESC' );
			}
		}
	}

	// сформировать ограничения по количеству для SQL-запроса
	private function makeSqlLimit() {
		if( $this->limit_count ) {
			if( $this->limit_start ) {
				$this->sql_limit = ' LIMIT '. $this->limit_start .', '. $this->limit_count;
			} else {
				$this->sql_limit = ' LIMIT '. $this->limit_count;
			}
		}
	}

	//------------------------------------------------------------------------------------------------------------------------------------------------------
	// SQL-запрос

	// загрузка данных из БД
	//
	//	$count_flag - флаг необходимости подсчёта общего колличества записей (bool)
	//	$limit_flag - флаг необходимости ограничить выборку по количеству строк (bool)
	//
	public function loadDataFromDB( $count_flag = true, $limit_flag = true ) {

		// при запросе общего числа записей сбросить нумерацию страниц
		if( $count_flag ) {
			$this->page = 1;
		}

		// ограничение на количество выборки
		$this->limit_count = ( $limit_flag ) ? $this->page_limit : false;
		$this->limit_start = ( $this->page - 1 ) * $this->page_limit;

		// сформировать SQL-запрос
		$this->makeSql();

		// получить общее колличество записей в БД для данного фильтра
		if( $count_flag ) {
			$this->countDataFromDB();
		}

		// запустить выборку
		$this->limitDataFromDB();
	}

	// получить общее колличество записей в БД
	private function countDataFromDB() {

		// получить общее колличество записей
		$sql = 'SELECT COUNT( DISTINCT t1.`ind` ) '. $this->getSqlFromWhereOnly();
		list( $this->count ) = DataBase::GetValues( $sql );

		// вернуть резульбтат запроса
		return $this->count;
	}

	// загрузка данных из БД, используя лимит вывода (шаблон)
	protected function limitDataFromDB() { return null; }
}

