<?php
/**
 * Copyright (c) 2012 Sam Tuke <samtuke@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

require_once "PHPUnit/Framework/TestCase.php";
require_once realpath( dirname(__FILE__).'/../../../lib/base.php' );
require_once realpath( dirname(__FILE__).'/../../../3rdparty/Crypt_Blowfish/Blowfish.php' );
require_once realpath( dirname(__FILE__).'/../../../3rdparty/mockery/Mockery.php' );
require_once realpath( dirname(__FILE__).'/../../../3rdparty/mockery/Mockery/Container.php' );
require_once realpath( dirname(__FILE__).'/../../../3rdparty/mockery/Mockery/Generator.php' );
require_once realpath( dirname(__FILE__).'/../../../3rdparty/mockery/Mockery/MockInterface.php' );
require_once realpath( dirname(__FILE__).'/../../../3rdparty/mockery/Mockery/Configuration.php' );
require_once realpath( dirname(__FILE__).'/../../../3rdparty/mockery/Mockery/CompositeExpectation.php' );
require_once realpath( dirname(__FILE__).'/../../../3rdparty/mockery/Mockery/ExpectationDirector.php' );
require_once realpath( dirname(__FILE__).'/../../../3rdparty/mockery/Mockery/Expectation.php' );
require_once realpath( dirname(__FILE__).'/../../../3rdparty/mockery/Mockery/Exception.php' );
require_once realpath( dirname(__FILE__).'/../../../3rdparty/mockery/Mockery/CountValidator/CountValidatorAbstract.php' );
require_once realpath( dirname(__FILE__).'/../../../3rdparty/mockery/Mockery/CountValidator/Exception.php' );
require_once realpath( dirname(__FILE__).'/../../../3rdparty/mockery/Mockery/CountValidator/Exact.php' );

use \Mockery as m;
use OCA\Encryption;

class Test_Util extends \PHPUnit_Framework_TestCase {
	
	function setUp() {
		
		// set content for encrypting / decrypting in tests
		$this->dataUrl = realpath( dirname(__FILE__).'/../lib/crypt.php' );
		$this->dataShort = 'hats';
		$this->dataLong = file_get_contents( realpath( dirname(__FILE__).'/../lib/crypt.php' ) );
		$this->legacyData = realpath( dirname(__FILE__).'/legacy-text.txt' );
		$this->legacyEncryptedData = realpath( dirname(__FILE__).'/legacy-encrypted-text.txt' );
		
		$this->userId = 'admin';
		$this->pass = 'admin';
		
		$keypair = Encryption\Crypt::createKeypair();
		
		$this->genPublicKey =  $keypair['publicKey'];
		$this->genPrivateKey = $keypair['privateKey'];
		
		$this->publicKeyDir =  '/' . 'public-keys';
		$this->encryptionDir =  '/' . $this->userId . '/' . 'files_encryption';
		$this->keyfilesPath = $this->encryptionDir . '/' . 'keyfiles';
		$this->publicKeyPath = $this->publicKeyDir . '/' . $this->userId . '.public.key'; // e.g. data/public-keys/admin.public.key
		$this->privateKeyPath = $this->encryptionDir . '/' . $this->userId . '.private.key'; // e.g. data/admin/admin.private.key
		
		$this->view = new OC_FilesystemView( '/admin' );
		
		$this->mockView = m::mock('OC_FilesystemView');
		$this->util = new Encryption\Util( $this->mockView, $this->userId );
	
	}
	
	function tearDown(){
	
		m::close();
	
	}
	
	/**
	 * @brief test that paths set during User construction are correct
	 */
	function testKeyPaths() {
	
		$mockView = m::mock('OC_FilesystemView');
		
		$util = new Encryption\Util( $mockView, $this->userId );
		
		$this->assertEquals( $this->publicKeyDir, $util->getPath( 'publicKeyDir' ) );
		$this->assertEquals( $this->encryptionDir, $util->getPath( 'encryptionDir' ) );
		$this->assertEquals( $this->keyfilesPath, $util->getPath( 'keyfilesPath' ) );
		$this->assertEquals( $this->publicKeyPath, $util->getPath( 'publicKeyPath' ) );
		$this->assertEquals( $this->privateKeyPath, $util->getPath( 'privateKeyPath' ) );
	
	}
	
	/**
	 * @brief test setup of encryption directories when they don't yet exist
	 */
	function testSetupServerSideNotSetup() {
	
		$mockView = m::mock('OC_FilesystemView');
		
		$mockView->shouldReceive( 'file_exists' )->times(4)->andReturn( false );
		$mockView->shouldReceive( 'mkdir' )->times(3)->andReturn( true );
		$mockView->shouldReceive( 'file_put_contents' )->withAnyArgs();
		
		$util = new Encryption\Util( $mockView, $this->userId );
		
		$this->assertEquals( true, $util->setupServerSide( $this->pass ) );
	
	}
	
	/**
	 * @brief test setup of encryption directories when they already exist
	 */
	function testSetupServerSideIsSetup() {
	
		$mockView = m::mock('OC_FilesystemView');
		
		$mockView->shouldReceive( 'file_exists' )->times(5)->andReturn( true );
		$mockView->shouldReceive( 'file_put_contents' )->withAnyArgs();
		
		$util = new Encryption\Util( $mockView, $this->userId );
		
		$this->assertEquals( true, $util->setupServerSide( $this->pass ) );
		
	}
	
	/**
	 * @brief test checking whether account is ready for encryption, when it isn't ready
	 */
	function testReadyNotReady() {
	
		$mockView = m::mock('OC_FilesystemView');
		
		$mockView->shouldReceive( 'file_exists' )->times(1)->andReturn( false );
		
		$util = new Encryption\Util( $mockView, $this->userId );
		
		$this->assertEquals( false, $util->ready() );
		
		# TODO: Add more tests here to check that if any of the dirs are 
		# then false will be returned. Use strict ordering?
		
	}
	
	/**
	 * @brief test checking whether account is ready for encryption, when it is ready
	 */
	function testReadyIsReady() {
	
		$mockView = m::mock('OC_FilesystemView');
		
		$mockView->shouldReceive( 'file_exists' )->times(3)->andReturn( true );
		
		$util = new Encryption\Util( $mockView, $this->userId );
		
		$this->assertEquals( true, $util->ready() );
		
		# TODO: Add more tests here to check that if any of the dirs are 
		# then false will be returned. Use strict ordering?
		
	}
	
	/**
	 * @brief test encryption using legacy blowfish method
	 */
	function testLegacyEncryptShort() {
	
		$crypted = $this->util->legacyEncrypt( $this->dataShort, $this->pass );

		$this->assertNotEquals( $this->dataShort, $crypted );
		
		# TODO: search inencrypted text for actual content to ensure it
		# genuine transformation
		
		return $crypted;
		
	}
	
	/**
	 * @brief test decryption using legacy blowfish method
	 * @depends testLegacyEncryptShort
	 */
	function testLegacyDecryptShort( $crypted ) {
	
		$decrypted = $this->util->legacyDecrypt( $crypted, $this->pass );
		
		$this->assertEquals( $this->dataShort, $decrypted );
		
	}

	/**
	 * @brief test encryption using legacy blowfish method
	 */
	function testLegacyEncryptLong() {
	
		$crypted = $this->util->legacyEncrypt( $this->dataLong, $this->pass );

		$this->assertNotEquals( $this->dataLong, $crypted );
		
		# TODO: search inencrypted text for actual content to ensure it
		# genuine transformation
		
		return $crypted;
		
	}
	
	/**
	 * @brief test decryption using legacy blowfish method
	 * @depends testLegacyEncryptLong
	 */
	function testLegacyDecryptLong( $crypted ) {
	
		$decrypted = $this->util->legacyDecrypt( $crypted, $this->pass );
		
		$this->assertEquals( $this->dataLong, $decrypted );
		
	}

	/**
	 * @brief test decryption using legacy blowfish method
	 * @depends testLegacyEncryptLong
	 */
	function testLegacyKeyRecryptKeyfileEncrypt( $crypted ) {
	
		$recrypted = $this->util->LegacyKeyRecryptKeyfile( $crypted, $this->pass, $this->genPublicKey, $this->pass );
		
		$this->assertNotEquals( $this->dataLong, $recrypted['data'] );
		
		return $recrypted;
		
		# TODO: search inencrypted text for actual content to ensure it
		# genuine transformation
		
	}

// 	/**
// 	 * @brief test decryption using legacy blowfish method
// 	 * @depends testLegacyEncryptLong
// 	 */
// 	function testLegacyKeyRecryptKeyfileDecrypt( $recrypted ) {
// 	
// 		$decrypted = Encryption\Crypt::keyDecryptKeyfile( $recrypted['data'], $recrypted['key'], $this->genPrivateKey );
// 		
// 		$this->assertEquals( $this->dataLong, $decrypted );
// 		
// 	}
	
//	// Cannot use this test for now due to hidden dependencies in OC_FileCache
// 	function testIsLegacyEncryptedContent() {
// 		
// 		$keyfileContent = OCA\Encryption\Crypt::symmetricEncryptFileContent( $this->legacyEncryptedData, 'hat' );
// 		
// 		$this->assertFalse( OCA\Encryption\Crypt::isLegacyEncryptedContent( $keyfileContent, '/files/admin/test.txt' ) );
// 		
// 		OC_FileCache::put( '/admin/files/legacy-encrypted-test.txt', $this->legacyEncryptedData );
// 		
// 		$this->assertTrue( OCA\Encryption\Crypt::isLegacyEncryptedContent( $this->legacyEncryptedData, '/files/admin/test.txt' ) );
// 		
// 	}

//	// Cannot use this test for now due to need for different root in OC_Filesystem_view class
// 	function testGetLegacyKey() {
// 		
// 		$c = new \OCA\Encryption\Util( $view, false );
// 
// 		$bool = $c->getLegacyKey( 'admin' );
//
//		$this->assertTrue( $bool );
// 		
// 		$this->assertTrue( $c->legacyKey );
// 		
// 		$this->assertTrue( is_int( $c->legacyKey ) );
// 		
// 		$this->assertTrue( strlen( $c->legacyKey ) == 20 );
//	
// 	}

//	// Cannot use this test for now due to need for different root in OC_Filesystem_view class
// 	function testLegacyDecrypt() {
// 
// 		$c = new OCA\Encryption\Util( $this->view, false );
// 		
// 		$bool = $c->getLegacyKey( 'admin' );
// 
// 		$encrypted = $c->legacyEncrypt( $this->data, $c->legacyKey );
// 		
// 		$decrypted = $c->legacyDecrypt( $encrypted, $c->legacyKey );
// 
// 		$this->assertEqual( $decrypted, $this->data );
// 	
// 	}

}