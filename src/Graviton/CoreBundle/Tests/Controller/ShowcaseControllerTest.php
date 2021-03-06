<?php
/**
 * functional test for /hans/showcase
 */

namespace Graviton\CoreBundle\Tests\Controller;

use Graviton\TestBundle\Test\RestTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional test for /hans/showcase
 *
 * @author   List of contributors <https://github.com/libgraviton/graviton/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class ShowcaseControllerTest extends RestTestCase
{
    /**
     * @const complete content type string expected on a resouce
     */
    const CONTENT_TYPE = 'application/json; charset=UTF-8; profile=http://localhost/schema/hans/showcase/item';

    /**
     * @const corresponding vendorized schema mime type
     */
    const COLLECTION_TYPE = 'application/json; charset=UTF-8; profile=http://localhost/schema/hans/showcase/collection';

    /**
     * suppress setup client and load fixtures of parent class
     *
     * @return void
     */
    public function setUp()
    {
    }

    /**
     * checks empty objects
     *
     * @return void
     */
    public function testGetEmptyObject()
    {
        $showCase = (object) [
            'anotherInt'            => 100,
            'aBoolean'              => true,
            'testField'             => ['en' => 'test'],
            'someOtherField'        => ['en' => 'other'],
            'contactCode'           => [
                'someDate'          => '2015-06-07T06:30:00+0000',
                'text'              => ['en' => 'text'],
            ],
            'contact'               => [
                'type'      => 'type',
                'value'     => 'value',
                'protocol'  => 'protocol',
                'uri'       => 'protocol:value',
            ],

            'nestedApps'            => [],
            'unstructuredObject'    => (object) [],
        ];

        $client = static::createRestClient();
        $client->post('/hans/showcase/', $showCase);
        $this->assertEquals(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $this->assertNull($client->getResults());

        $url = $client->getResponse()->headers->get('Location');
        $this->assertNotNull($url);

        $client = static::createRestClient();
        $client->request('GET', $url);
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $created = $client->getResults();
        $this->assertEquals($showCase->nestedApps, $created->nestedApps);
        $this->assertEquals($showCase->unstructuredObject, $created->unstructuredObject);
    }

    /**
     * see how our missing fields are explained to us
     *
     * @return void
     */
    public function testMissingFields()
    {
        $document = json_decode(
            file_get_contents(dirname(__FILE__).'/../resources/showcase-incomplete.json'),
            true
        );

        $client = static::createRestClient();
        $client->post('/hans/showcase', $document);

        $expectedErrors = [];
        $expectedError = new \stdClass();
        $expectedError->propertyPath = 'children[someOtherField]';
        $expectedError->message = 'This value is not valid.';
        $expectedErrors[] = $expectedError;
        $notNullError = new \stdClass();
        $notNullError->propertyPath = 'data.aBoolean';
        $notNullError->message = 'The value "" is not a valid boolean.';
        $expectedErrors[] = $notNullError;

        $this->assertJsonStringEqualsJsonString(
            json_encode($expectedErrors),
            json_encode($client->getResults())
        );
    }

    /**
     * see how our empty fields are explained to us
     *
     * @return void
     */
    public function testEmptyAllFields()
    {
        $document = [
            'anotherInt'  => 6555488894525,
            'testField'   => ['en' => 'a test string'],
            'aBoolean'    => '',
            'contactCode' => [
                'text'     => ['en' => 'Some Text'],
                'someDate' => '1984-05-01T00:00:00+0000',
            ],
            'contact'     => [
                'type'      => '',
                'value'     => '',
                'protocol'  => '',
            ],
        ];

        $client = static::createRestClient();
        $client->post('/hans/showcase', $document);

        $this->assertEquals(
            Response::HTTP_BAD_REQUEST,
            $client->getResponse()->getStatusCode()
        );
        $this->assertEquals(
            [
                (object) [
                    'propertyPath'  => 'children[someOtherField]',
                    'message'       => 'This value is not valid.',
                ],
                (object) [
                    'propertyPath'  => 'data.aBoolean',
                    'message'       => 'The value "" is not a valid boolean.',
                ],
                (object) [
                    'propertyPath'  => 'data.contact.type',
                    'message'       => 'This value should not be blank.',
                ],
                (object) [
                    'propertyPath'  => 'data.contact.protocol',
                    'message'       => 'This value should not be blank.',
                ],
                (object) [
                    'propertyPath'  => 'data.contact.value',
                    'message'       => 'This value should not be blank.',
                ],
            ],
            $client->getResults()
        );
    }

    /**
     * see how our empty fields are explained to us
     *
     * @return void
     */
    public function testEmptyFields()
    {
        $document = [
            'anotherInt'  => 6555488894525,
            'testField'   => ['en' => 'a test string'],
            'aBoolean'    => true,
            'contactCode' => [
                'text'     => ['en' => 'Some Text'],
                'someDate' => '1984-05-01T00:00:00+0000',
            ],
            'contact'     => [
                'type'      => 'abc',
                'value'     => '',
                'protocol'  => '',
            ],
        ];

        $client = static::createRestClient();
        $client->post('/hans/showcase', $document);

        $this->assertEquals(
            Response::HTTP_BAD_REQUEST,
            $client->getResponse()->getStatusCode()
        );
        $this->assertEquals(
            [
                (object) [
                    'propertyPath'  => 'children[someOtherField]',
                    'message'       => 'This value is not valid.',
                ],
                (object) [
                    'propertyPath'  => 'data.contact.protocol',
                    'message'       => 'This value should not be blank.',
                ],
                (object) [
                    'propertyPath'  => 'data.contact.value',
                    'message'       => 'This value should not be blank.',
                ],
            ],
            $client->getResults()
        );
    }

    /**
     * insert various formats to see if all works as expected
     *
     * @dataProvider postCreationDataProvider
     *
     * @param string $filename filename
     *
     * @return void
     */
    public function testPost($filename)
    {
        // showcase contains some datetime fields that we need rendered as UTC in the case of this test
        ini_set('date.timezone', 'UTC');
        $document = json_decode(
            file_get_contents($filename),
            false
        );

        $client = static::createRestClient();
        $client->post('/hans/showcase/', $document);
        $response = $client->getResponse();

        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());

        $client = static::createRestClient();
        $client->request('GET', $response->headers->get('Location'));

        $result = $client->getResults();

        // unset id as we cannot compare and don't care
        $this->assertNotNull($result->id);
        unset($result->id);

        $this->assertJsonStringEqualsJsonString(
            json_encode($document),
            json_encode($result)
        );
    }

    /**
     * Provides test sets for the testPost() test.
     *
     * @return array
     */
    public function postCreationDataProvider()
    {
        $basePath = dirname(__FILE__).'/../resources/';

        return array(
            'minimal' => array($basePath.'showcase-minimal.json'),
            'complete' => array($basePath.'showcase-complete.json')
        );
    }

    /**
     * test if we can save & retrieve extrefs inside 'free form objects'
     *
     * @return void
     */
    public function testFreeFormExtRefs()
    {
        $minimalExample = $this->postCreationDataProvider()['minimal'][0];

        $document = json_decode(
            file_get_contents($minimalExample),
            false
        );

        $document->id = 'dynextreftest';

        // insert some refs!
        $document->unstructuredObject = new \stdClass();
        $document->unstructuredObject->testRef = new \stdClass();
        $document->unstructuredObject->testRef->{'$ref'} = 'http://localhost/hans/showcase/500';

        // let's go more deep..
        $document->unstructuredObject->go = new \stdClass();
        $document->unstructuredObject->go->more = new \stdClass();
        $document->unstructuredObject->go->more->deep = new \stdClass();
        $document->unstructuredObject->go->more->deep->{'$ref'} = 'http://localhost/hans/showcase/500';

        // array?
        $document->unstructuredObject->refArray = [];
        $document->unstructuredObject->refArray[0] = new \stdClass();
        $document->unstructuredObject->refArray[0]->{'$ref'} = 'http://localhost/core/app/dude';
        $document->unstructuredObject->refArray[1] = new \stdClass();
        $document->unstructuredObject->refArray[1]->{'$ref'} = 'http://localhost/core/app/dude2';

        $client = static::createRestClient();
        $client->put('/hans/showcase/'.$document->id, $document);

        $this->assertEquals(204, $client->getResponse()->getStatusCode());

        $client = static::createRestClient();
        $client->request('GET', '/hans/showcase/'.$document->id);

        // all still the same?
        $this->assertEquals($document, $client->getResults());
    }

    /**
     * are extra fields denied?
     *
     * @return void
     */
    public function testExtraFieldPost()
    {
        ini_set('date.timezone', 'UTC');
        $document = json_decode(
            file_get_contents(dirname(__FILE__).'/../resources/showcase-minimal.json'),
            false
        );
        $document->extraFields = "nice field";

        $client = static::createRestClient();
        $client->post('/hans/showcase', $document);
        $this->assertEquals(400, $client->getResponse()->getStatusCode());

        $expectedErrors = [];
        $expectedErrors[0] = new \stdClass();
        $expectedErrors[0]->propertyPath = "";
        $expectedErrors[0]->message = "This form should not contain extra fields.";

        $this->assertJsonStringEqualsJsonString(
            json_encode($expectedErrors),
            json_encode($client->getResults())
        );
    }

    /**
     * Test RQL select statement
     *
     * @return void
     */
    public function testRqlSelect()
    {
        $this->loadFixtures(
            ['GravitonDyn\ShowCaseBundle\DataFixtures\MongoDB\LoadShowCaseData'],
            null,
            'doctrine_mongodb'
        );

        $filtred = json_decode(
            file_get_contents(dirname(__FILE__).'/../resources/showcase-rql-select-filtred.json'),
            false
        );

        $fields = [
            'someFloatyDouble',
            'contact.uri',
            'contactCode.text.en',
            'unstructuredObject.booleanField',
            'unstructuredObject.hashField.someField',
            'unstructuredObject.nestedArrayField.anotherField',
            'nestedCustomers.$ref'
        ];
        $rqlSelect = 'select('.implode(',', array_map([$this, 'encodeRqlString'], $fields)).')';

        $client = static::createRestClient();
        $client->request('GET', '/hans/showcase/?'.$rqlSelect);
        $this->assertEquals($filtred, $client->getResults());


        foreach ([
                     '500' => $filtred[0],
                     '600' => $filtred[1],
                 ] as $id => $item) {
            $client = static::createRestClient();
            $client->request('GET', '/hans/showcase/'.$id.'?'.$rqlSelect);
            $this->assertEquals($item, $client->getResults());
        }
    }

    /**
     * Test to see if we can do like() searches on identifier fields
     *
     * @return void
     */
    public function testLikeSearchOnIdentifierField()
    {
        // Load fixtures
        $this->loadFixtures(
            ['GravitonDyn\ShowCaseBundle\DataFixtures\MongoDB\LoadShowCaseData'],
            null,
            'doctrine_mongodb'
        );

        $client = static::createRestClient();
        $client->request('GET', '/hans/showcase/?like(id,5*)');

        // we should only get 1 ;-)
        $this->assertEquals(1, count($client->getResults()));

        $client = static::createRestClient();
        $client->request('GET', '/hans/showcase/?like(id,*0)');

        // this should get both
        $this->assertEquals(2, count($client->getResults()));
    }

    /**
     * Test PATCH for deep nested attribute
     *
     * @return void
     */
    public function testPatchDeepNestedProperty()
    {
        // Load fixtures
        $this->loadFixtures(
            ['GravitonDyn\ShowCaseBundle\DataFixtures\MongoDB\LoadShowCaseData'],
            null,
            'doctrine_mongodb'
        );

        // Apply PATCH request
        $client = static::createRestClient();
        $patchJson = json_encode(
            [
                [
                    'op' => 'replace',
                    'path' => '/unstructuredObject/hashField/anotherField',
                    'value' => 'changed nested hash field with patch'
                ]
            ]
        );
        $client->request('PATCH', '/hans/showcase/500', array(), array(), array(), $patchJson);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        // Get changed showcase
        $client = static::createRestClient();
        $client->request('GET', '/hans/showcase/500');

        $result = $client->getResults();
        $this->assertEquals(
            'changed nested hash field with patch',
            $result->unstructuredObject->hashField->anotherField
        );
    }

    /**
     * Test success PATCH method - response headers contains link to resource
     *
     * @return void
     */
    public function testPatchSuccessResponseHeaderContainsResourceLink()
    {
        // Load fixtures
        $this->loadFixtures(
            ['GravitonDyn\ShowCaseBundle\DataFixtures\MongoDB\LoadShowCaseData'],
            null,
            'doctrine_mongodb'
        );

        // Apply PATCH request
        $client = static::createRestClient();
        $patchJson = json_encode(
            [
                [
                    'op' => 'replace',
                    'path' => '/testField/en',
                    'value' => 'changed value'
                ]
            ]
        );
        $client->request('PATCH', '/hans/showcase/500', array(), array(), array(), $patchJson);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        $this->assertEquals(
            '/hans/showcase/500',
            $client->getResponse()->headers->get('Content-Location')
        );
    }

    /**
     * Test PATCH method - remove/change ID not allowed
     *
     * @return void
     */
    public function testPatchRemoveAndChangeIdNotAllowed()
    {
        // Load fixtures
        $this->loadFixtures(
            ['GravitonDyn\ShowCaseBundle\DataFixtures\MongoDB\LoadShowCaseData'],
            null,
            'doctrine_mongodb'
        );

        // Apply PATCH request
        $client = static::createRestClient();
        $patchJson = json_encode(
            [
                [
                    'op' => 'remove',
                    'path' => '/id'
                ]
            ]
        );
        $client->request('PATCH', '/hans/showcase/500', array(), array(), array(), $patchJson);
        $this->assertEquals(400, $client->getResponse()->getStatusCode());
    }

    /**
     * Test PATCH: add property to free object structure
     *
     * @return void
     */
    public function testPatchAddPropertyToFreeObject()
    {
        // Load fixtures
        $this->loadFixtures(
            ['GravitonDyn\ShowCaseBundle\DataFixtures\MongoDB\LoadShowCaseData'],
            null,
            'doctrine_mongodb'
        );

        // Apply PATCH request
        $client = static::createRestClient();
        $patchJson = json_encode(
            [
                [
                    'op' => 'add',
                    'path' => '/unstructuredObject/hashField/newAddedField',
                    'value' => 'new field value'
                ]
            ]
        );
        $client->request('PATCH', '/hans/showcase/500', array(), array(), array(), $patchJson);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        // Get changed showcase
        $client = static::createRestClient();
        $client->request('GET', '/hans/showcase/500');

        $result = $client->getResults();
        $this->assertEquals(
            'new field value',
            $result->unstructuredObject->hashField->newAddedField
        );
    }

    /**
     * Test PATCH for $ref attribute
     *
     * @return void
     * @incomplete
     */
    public function testApplyPatchForRefAttribute()
    {
        // Load fixtures
        $this->loadFixtures(
            [
                'GravitonDyn\ShowCaseBundle\DataFixtures\MongoDB\LoadShowCaseData'
            ],
            null,
            'doctrine_mongodb'
        );

        // Apply PATCH request
        $client = static::createRestClient();
        $patchJson = json_encode(
            [
                [
                    'op' => 'replace',
                    'path' => '/nestedApps/0/$ref',
                    'value' => 'http://localhost/core/app/admin'
                ]
            ]
        );
        $client->request('PATCH', '/hans/showcase/500', array(), array(), array(), $patchJson);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        // Check patched result
        $client = static::createRestClient();
        $client->request('GET', '/hans/showcase/500');

        $result = $client->getResults();
        $this->assertEquals(
            'http://localhost/core/app/admin',
            $result->nestedApps[0]->{'$ref'}
        );
    }

    /**
     * Test PATCH: apply patch which results to invalid Showcase schema
     *
     * @return void
     */
    public function testPatchToInvalidShowcase()
    {
        // Load fixtures
        $this->loadFixtures(
            ['GravitonDyn\ShowCaseBundle\DataFixtures\MongoDB\LoadShowCaseData'],
            null,
            'doctrine_mongodb'
        );

        // Apply PATCH request, remove required field
        $client = static::createRestClient();
        $patchJson = json_encode(
            [
                [
                    'op' => 'remove',
                    'path' => '/anotherInt'
                ]
            ]
        );
        $client->request('PATCH', '/hans/showcase/500', array(), array(), array(), $patchJson);
        $this->assertEquals(400, $client->getResponse()->getStatusCode());

        // Check that Showcase has not been changed
        $client = static::createRestClient();
        $client->request('GET', '/hans/showcase/500');

        $result = $client->getResults();
        $this->assertObjectHasAttribute('anotherInt', $result);
    }

    /**
     * Test PATCH: remove element from array
     *
     * @return void
     */
    public function testRemoveFromArrayPatch()
    {
        // Load fixtures
        $this->loadFixtures(
            ['GravitonDyn\ShowCaseBundle\DataFixtures\MongoDB\LoadShowCaseData'],
            null,
            'doctrine_mongodb'
        );

        // Apply PATCH request, remove nested app, initially there are 2 apps
        $client = static::createRestClient();
        $patchJson = json_encode(
            [
                [
                    'op' => 'remove',
                    'path' => '/nestedApps/0'
                ]
            ]
        );
        $client->request('PATCH', '/hans/showcase/500', array(), array(), array(), $patchJson);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        // Check patched result
        $client = static::createRestClient();
        $client->request('GET', '/hans/showcase/500');

        $result = $client->getResults();
        $this->assertEquals(1, count($result->nestedApps));
        $this->assertEquals('http://localhost/core/app/admin', $result->nestedApps[0]->{'$ref'});
    }

    /**
     * Test PATCH: add new element to array
     *
     * @return void
     */
    public function testAddElementToSpecificIndexInArrayPatch()
    {
        // Load fixtures
        $this->loadFixtures(
            ['GravitonDyn\ShowCaseBundle\DataFixtures\MongoDB\LoadShowCaseData'],
            null,
            'doctrine_mongodb'
        );

        // Apply PATCH request, add new element
        $client = static::createRestClient();
        $newElement = ['name' => 'element three'];
        $patchJson = json_encode(
            [
                [
                    'op' => 'add',
                    'path' => '/nestedArray/1',
                    'value' => $newElement
                ]
            ]
        );
        $client->request('PATCH', '/hans/showcase/500', array(), array(), array(), $patchJson);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        // Check patched result
        $client = static::createRestClient();
        $client->request('GET', '/hans/showcase/500');

        $result = $client->getResults();
        $this->assertEquals(3, count($result->nestedArray));
        $this->assertJsonStringEqualsJsonString(
            json_encode($newElement),
            json_encode($result->nestedArray[1])
        );
    }

    /**
     * Test PATCH: add complex object App to array
     *
     * @group ref
     * @return void
     */
    public function testPatchAddComplexObjectToSpecificIndexInArray()
    {
        // Load fixtures
        $this->loadFixtures(
            ['GravitonDyn\ShowCaseBundle\DataFixtures\MongoDB\LoadShowCaseData'],
            null,
            'doctrine_mongodb'
        );

        // Apply PATCH request, add new element
        $client = static::createRestClient();
        $newApp = ['ref' => 'http://localhost/core/app/admin'];
        $patchJson = json_encode(
            [
                [
                    'op' => 'add',
                    'path' => '/nestedApps/0',
                    'value' => $newApp
                ]
            ]
        );
        $client->request('PATCH', '/hans/showcase/500', array(), array(), array(), $patchJson);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        // Check patched result
        $client = static::createRestClient();
        $client->request('GET', '/hans/showcase/500');

        $result = $client->getResults();
        $this->assertEquals(3, count($result->nestedApps));
        $this->assertEquals(
            'http://localhost/core/app/admin',
            $result->nestedApps[0]->{'$ref'}
        );
    }

    /**
     * Test PATCH: add complex object App to array
     *
     * @group ref
     * @return void
     */
    public function testPatchAddComplexObjectToTheEndOfArray()
    {
        // Load fixtures
        $this->loadFixtures(
            ['GravitonDyn\ShowCaseBundle\DataFixtures\MongoDB\LoadShowCaseData'],
            null,
            'doctrine_mongodb'
        );

        // Apply PATCH request, add new element
        $client = static::createRestClient();
        $newApp = ['ref' => 'http://localhost/core/app/test'];
        $patchJson = json_encode(
            [
                [
                    'op' => 'add',
                    'path' => '/nestedApps/-',
                    'value' => $newApp
                ]
            ]
        );
        $client->request('PATCH', '/hans/showcase/500', array(), array(), array(), $patchJson);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        // Check patched result
        $client = static::createRestClient();
        $client->request('GET', '/hans/showcase/500');

        $result = $client->getResults();
        $this->assertEquals(3, count($result->nestedApps));
        $this->assertEquals(
            'http://localhost/core/app/test',
            $result->nestedApps[2]->{'$ref'}
        );
    }

    /**
     * Test PATCH: test operation to undefined index
     *
     * @group ref
     * @return void
     */
    public function testPatchTestOperationToUndefinedIndexThrowsException()
    {
        // Load fixtures
        $this->loadFixtures(
            ['GravitonDyn\ShowCaseBundle\DataFixtures\MongoDB\LoadShowCaseData'],
            null,
            'doctrine_mongodb'
        );

        // Apply PATCH request, add new element
        $client = static::createRestClient();
        $newApp = ['ref' => 'http://localhost/core/app/test'];
        $patchJson = json_encode(
            [
                [
                    'op' => 'test',
                    'path' => '/nestedApps/9',
                    'value' => $newApp
                ]
            ]
        );
        $client->request('PATCH', '/hans/showcase/500', array(), array(), array(), $patchJson);
        $this->assertEquals(400, $client->getResponse()->getStatusCode());
    }

    /**
     * Test PATCH: add complex object App to array
     *
     * @group ref
     * @return void
     */
    public function testPatchAddElementToUndefinedIndexResponseAsBadRequest()
    {
        // Load fixtures
        $this->loadFixtures(
            ['GravitonDyn\ShowCaseBundle\DataFixtures\MongoDB\LoadShowCaseData'],
            null,
            'doctrine_mongodb'
        );

        // Apply PATCH request, add new element
        $client = static::createRestClient();
        $newApp = ['ref' => 'http://localhost/core/app/admin'];
        $patchJson = json_encode(
            [
                [
                    'op' => 'add',
                    'path' => '/nestedApps/9',
                    'value' => $newApp
                ]
            ]
        );
        $client->request('PATCH', '/hans/showcase/500', array(), array(), array(), $patchJson);
        $this->assertEquals(400, $client->getResponse()->getStatusCode());

        // Check that patched document not changed
        $client = static::createRestClient();
        $client->request('GET', '/hans/showcase/500');

        $result = $client->getResults();
        $this->assertEquals(2, count($result->nestedApps));
    }

    /**
     * Encode string value in RQL
     *
     * @param string $value String value
     * @return string
     */
    private function encodeRqlString($value)
    {
        return strtr(
            rawurlencode($value),
            [
                '-' => '%2D',
                '_' => '%5F',
                '.' => '%2E',
                '~' => '%7E',
            ]
        );
    }

    /**
     * Trigger a 301 Status code
     *
     * @param string $url         requested url
     * @param string $redirectUrl redirected url
     * @dataProvider rqlDataProvider
     * @return void
     */
    public function testTrigger301($url, $redirectUrl)
    {
        $client = static::createRestClient();
        $client->request('GET', $url);
        $this->assertEquals(301, $client->getResponse()->getStatusCode());
        $this->assertEquals($redirectUrl, $client->getResponse()->headers->get('Location'));
    }

    /**
     * Provides urls for the testTrigger301() test.
     *
     * @return array
     */
    public function rqlDataProvider()
    {
        return [
            'rql' => ['url' => '/hans/showcase?id=blah' , 'redirect_url' => 'http://localhost/hans/showcase/?id=blah'],
            'noRql' => ['url' => '/hans/showcase' , 'redirect_url' => 'http://localhost/hans/showcase/']
        ];
    }

    /**
     * test finding of showcases by ref
     *
     * @dataProvider findByExtrefProvider
     *
     * @param string  $field which reference to search in
     * @param mixed   $url   ref to search for
     * @param integer $count number of results to expect
     *
     * @return void
     */
    public function testFindByExtref($field, $url, $count)
    {
        $this->loadFixtures(
            ['GravitonDyn\ShowCaseBundle\DataFixtures\MongoDB\LoadShowCaseData'],
            null,
            'doctrine_mongodb'
        );

        $url = sprintf(
            '/hans/showcase/?%s=%s',
            $this->encodeRqlString($field),
            $this->encodeRqlString($url)
        );

        $client = static::createRestClient();
        $client->request('GET', $url);
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $this->assertCount($count, $client->getResults());
    }

    /**
     * @return array
     */
    public function findByExtrefProvider()
    {
        return [
            'find a linked record when searching for "tablet" ref by array field' => [
                'nestedApps.0.$ref',
                'http://localhost/core/app/tablet',
                1
            ],
            'find a linked record when searching for "admin" ref by array field' => [
                'nestedApps.0.$ref',
                'http://localhost/core/app/admin',
                1
            ],
            'find nothing when searching for inextistant (and unlinked) ref by array field' => [
                'nestedApps.0.$ref',
                'http://localhost/core/app/inexistant',
                0
            ],
            'return nothing when searching with incomplete ref by array field' => [
                'nestedApps.0.$ref',
                'http://localhost/core/app',
                0
            ],
        ];
    }
}
