<?php declare( strict_types = 1 );

use Peroks\Model\Model;
use Peroks\Model\ModelData;
use Peroks\Model\ModelException;
use PHPUnit\Framework\TestCase;

/**
 * Model test case.
 *
 * @author Per Egil Roksvaag
 * @copyright Per Egil Roksvaag
 * @license MIT
 */
final class ModelTest extends TestCase {

	protected function setUp(): void {
		require_once dirname( __FILE__, 2 ) . '/vendor/autoload.php';

		$source = dirname( __FILE__, 2 ) . '/store/model.json';
		is_writeable( $source ) && unlink( $source );
	}

	/**
	 * Data provider for testSearchProductRequest().
	 *
	 * @return array[][]
	 */
	public function getModels(): array {
		require_once dirname( __FILE__ ) . '/Tour.php';
		require_once dirname( __FILE__ ) . '/ExtendedTour.php';

		$data = [
			'id'      => 'lord-of-the-rings-tour',
			'name'    => 'Lord of the Rings Tour',
			'country' => 'New Zealand',
			'cities'  => [ 'Auckland', 'Wellington' ],
			'details' => (object) [ 'language' => 'english', 'type' => 'public' ],
		];

		return [
			[ Model::create( $data ), $data ],
			[ Tour::create( $data ), $data ],
			[ Tour::create( (object) $data ), $data ],
			[ Tour::create( Model::create( $data ) ), $data ],
			[ ExtendedTour::create( Tour::create( $data ) ), $data ],
		];
	}

	/**
	 * @dataProvider getModels
	 *
	 * @param Model $model
	 * @param array $data
	 */
	public function testValidate( Model $model, array $data ): void {
		if ( $model instanceof ExtendedTour ) {
			$this->expectException( ModelException::class );
			$model->validate();
			$this->assertNotEquals( $model->getArrayCopy(), $data );
		} else {
			$this->assertInstanceOf( Model::class, $model->validate() );
			$this->assertEquals( $model->getArrayCopy(), $data );
		}
	}

	/**
	 * @dataProvider getModels
	 *
	 * @param Model $model
	 * @param array $data
	 */
	public function testString( Model $model, array $data ): void {
		$this->assertIsString( $model['name'] );
		$this->assertIsString( $model->name );
		$this->assertEquals( $model['name'], $data['name'] );
		$this->assertEquals( $model->name, $data['name'] );
		$this->assertEquals( $model->name, $model['name'] );

		$name = $model->name;
		$name = '';
		$this->assertNotEquals( $model->name, $name );
		$this->assertEquals( $model->name, $data['name'] );
		$this->assertEquals( $model->name, $model['name'] );

		$name = &$model->name;
		$name = '';
		$this->assertEquals( $model->name, $name );
		$this->assertNotEquals( $model->name, $data['name'] );
		$this->assertEquals( $model->name, $model['name'] );
	}

	/**
	 * @dataProvider getModels
	 *
	 * @param Model $model
	 * @param array $data
	 */
	public function testArray( Model $model, array $data ): void {
		$count = count( $data['cities'] );

		$this->assertIsArray( $model['cities'] );
		$this->assertIsArray( $model->cities );

		$model['cities'][] = 'Queenstown';
		$model->cities[]   = 'Glenorchy';
		$this->assertContains( 'Queenstown', $model['cities'] );
		$this->assertContains( 'Queenstown', $model->cities );
		$this->assertContains( 'Glenorchy', $model['cities'] );
		$this->assertContains( 'Glenorchy', $model->cities );
		$this->assertCount( $count + 2, $model['cities'] );
		$this->assertCount( $count + 2, $model->cities );
		$this->assertEquals( $model['cities'], $model->cities );

		array_pop( $model['cities'] );
		array_pop( $model->cities );
		$this->assertEquals( $model['cities'], $data['cities'] );
		$this->assertEquals( $model->cities, $data['cities'] );
		$this->assertEquals( $model['cities'], $model->cities );
	}

	/**
	 * @dataProvider getModels
	 *
	 * @param Model $model
	 * @param array $data
	 */
	public function testObject( Model $model, array $data ): void {
		$count = count( get_object_vars( $data['details'] ) );

		$this->assertIsObject( $model['details'] );
		$this->assertIsObject( $model->details );
		$this->assertEquals( $model['details'], $data['details'] );
		$this->assertEquals( $model->details, $data['details'] );
		$this->assertEquals( $model->details, $model['details'] );

		$model['details']->duration = '1h';
		$model->details->capasity   = 10;
		$this->assertEquals( '1h', $model['details']->duration );
		$this->assertEquals( '1h', $model->details->duration );
		$this->assertEquals( 10, $model['details']->capasity );
		$this->assertEquals( 10, $model->details->capasity );

		$this->assertCount( $count + 2, get_object_vars( $data['details'] ) );
		$this->assertCount( $count + 2, get_object_vars( $model['details'] ) );
		$this->assertCount( $count + 2, get_object_vars( $model->details ) );

		$this->assertEquals( $model->details, $data['details'] );
		$this->assertEquals( $model['details'], $data['details'] );
		$this->assertEquals( $model->details, $data['details'] );
		$this->assertEquals( $model->details, $model['details'] );

		unset( $model['details']->duration );
		unset( $model->details->capasity );
		$this->assertEquals( $model['details'], $data['details'] );
		$this->assertEquals( $model->details, $data['details'] );
		$this->assertEquals( $model->details, $model['details'] );

		$this->assertCount( $count, get_object_vars( $data['details'] ) );
		$this->assertCount( $count, get_object_vars( $model['details'] ) );
		$this->assertCount( $count, get_object_vars( $model->details ) );
	}

	/**
	 * @dataProvider getModels
	 *
	 * @param Model $model
	 * @param array $data
	 */
	public function testProperty( Model $model, array $data ): void {
		$this->assertIsArray( $model::properties() );
		$this->assertNotEmpty( $model->id() );

		if ( $model instanceof ExtendedTour ) {
			$this->assertCount( count( Tour::properties() ) + 1, $model::properties() );
		} elseif ( $model instanceof Tour ) {
			$this->assertNotEmpty( $model::properties() );
		} else {
			$this->assertEmpty( $model::properties() );
		}
	}

	/**
	 * @dataProvider getModels
	 *
	 * @param Model $model
	 * @param array $data
	 */
	public function testData( Model $model, array $data ): void {
		$count = count( $data );

		if ( $model instanceof ExtendedTour ) {
			$this->assertIsArray( $model->data() );
			$this->assertEquals( $model->data( ModelData::COMPACT ), $data );
			$this->assertCount( $count + 1, $model->data( ModelData::FULL ) );
			$this->assertCount( $count + 1, $model->data( ModelData::FULL ) );
		} elseif ( $model instanceof Tour ) {
			$this->assertEquals( $model->data( ModelData::COMPACT ), $data );
			$this->assertEquals( $model->data( ModelData::FULL ), $data );
			$this->assertEquals( $model->data( ModelData::RAW ), $data );
		} else {
			$this->assertEmpty( $model->data( ModelData::COMPACT ) );
			$this->assertEmpty( $model->data( ModelData::FULL ) );
			$this->assertEquals( $model->data( ModelData::RAW ), $data );
		}
	}

	/**
	 * @dataProvider getModels
	 *
	 * @param Model $model
	 * @param array $data
	 */
	public function testJson( Model $model, array $data ): void {
		$this->assertJson( (string) $model );
		$this->assertJson( json_encode( $model ) );
	}

	/**
	 * @dataProvider getModels
	 *
	 * @param Model $model
	 * @param array $data
	 */
	public function testSerialize( Model $model, array $data ): void {
		$ser = serialize( $model );
		$this->assertIsString( $ser );
		$this->assertInstanceOf( Model::class, unserialize( $ser ) );
	}
}
