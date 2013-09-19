<?php
/**
 * @file
 * Superclass of all independent API objects in the Mint hierarchy.
 *
 * @author Daniel Johnson <djohnson@apigee.com>
 * @since 10 May 2013
 */

namespace Apigee\Mint\Base;

use \Apigee\Exceptions\ParameterException as ParameterException;
use \Apigee\Exceptions\ResponseException as ResponseException;

/**
 * Class BaseObject
 *
 * Descendants of this class should define base_url, wrapper_tag and
 * id_field in their constructor.
 *
 * @package Apigee\Mint\Base
 */
abstract class BaseObject extends \Apigee\Util\APIObject {

  /**
   * @var string
   * Base URL for this object's class. This will generally be
   * 'mint/$objectName'.
   */
  protected $base_url;

  /**
   * @var string
   * In collections, this is the name of the parent object in the collection.
   * It is usually a camelCase version of the object name.
   */
  protected $wrapper_tag;

  /**
   * @var string
   * The name of the field that provides the primary key of the object.
   */
  protected $id_field;

  /**
   * @var bool
   * If the $id_field can be autogenerated, this should be TRUE, else FALSE.
   */
  protected $id_is_autogenerated = TRUE;

  /**
   * Creates a blank instance of __CLASS__ with the same constructor parameters
   * as the class that is doing the instantiation.
   *
   * @return \Apigee\Mint\Base\BaseObject
   */
  public abstract function instantiateNew();

  /**
   * Given an associative array from the raw JSON response, populates the
   * object with that data.
   *
   * @param array $data
   * @param bool $reset
   * @return void
   */
  public abstract function loadFromRawData($data, $reset = FALSE);

  /**
   * Returns all member variables to their default values.
   *
   * @return mixed
   */
  protected abstract function initValues();

  /**
   * Returns a JSON representation of the object.
   *
   * @return mixed
   */
  public abstract function __toString();

  /**
   * Returns a listing of this class of objects.
   *
   * @param null|int $page_num
   * @param int $page_size
   * @return array
   */
  public function getList($page_num = NULL, $page_size = 20) {
    // When page_num is NULL, fetch all orgs
    $query = array();
    $page_num = intval($page_num);
    $page_size = intval($page_size);
    if ($page_num < 1 || $page_size < 1) {
      $query['all'] = 'true';
    }
    else {
      $query['page'] = $page_num;
      $query['size'] = $page_size;
    }
    $url = $this->base_url . '?' . http_build_query($query);
    $this->client->get($url);
    $response = $this->getResponse();

    $return_objects = array();

    foreach ($response[$this->wrapper_tag] as $response_data) {
      $obj = $this->instantiateNew();
      $obj->loadFromRawData($response_data);
      $return_objects[] = $obj;
    }
    return $return_objects;
  }

  /**
   * Calls the API to populate the object.
   *
   * @param null|string $id
   * @throws \Apigee\Exceptions\ParameterException
   */
  public function load($id = NULL) {
    if (!isset($id)) {
      $id = $this->{$this->id_field};
    }
    if (!isset($id)) {
      throw new ParameterException('No object identifier was specified.');
    }

    $this->initValues();

    $url = $this->base_url . '/' . rawurlencode($id);
    $this->client->get($url);
    $response = $this->getResponse();
    $this->loadFromRawData($response);
  }

  /**
   * Calls the API to save the object.
   *
   * If you know for certain that you are creating a new object, you should
   * pass $save_method of 'create'.  If you know for certain that you are
   * updating an existing object, you should pass $save_method of 'update'.
   * If you pass 'auto', it will try to create, and failing that, will try
   * to update an existing one. This is more robust but less efficient.
   *
   * @param string $save_method
   * @throws \Apigee\Exceptions\ResponseException|\Exception
   * @throws \Apigee\Exceptions\ParameterException
   */
  public function save($save_method = 'auto') {
    if ($save_method != 'auto' && $save_method != 'create' && $save_method != 'update') {
      throw new ParameterException('Valid save methods are create, update or auto.');
    }

    if (!isset($this->{$this->id_field})) {
      // If the ID for this object type is auto-generated, and the ID field
      // itself is empty, force an object create.
      if ($this->id_is_autogenerated && $save_method != 'update') {
        $save_method = 'create';
      }
      else {
        // Under these circumstances,
        throw new ParameterException('No object identifier (' . $this->id_field . ') was specified.');
      }
    }
    $url = $this->base_url;
    $payload = (string)$this;

    if ($save_method == 'auto' || $save_method == 'create') {
      $this->client->post($url, $payload);
      try {
        $response = $this->getResponse();
        // Reload object from response data in case any auto-generated fields
        // have been updated.
        $this->loadFromRawData($response);
      }
      catch (ResponseException $e) {
        if ($save_method == 'auto' && $e->getCode() == 409) {
          // If we tried to create, and the object already exists, try updating
          // via PUT instead.
          $this->save('update');
        }
        else {
          throw $e;
        }
      }
    }
    else {
      $url .= '/' . rawurlencode($this->{$this->id_field});
      $this->client->put($url, $payload);
      $response = $this->getResponse();
      // Reload object from response data in case any auto-generated fields
      // have been updated.
      $this->loadFromRawData($response);
    }
  }

  /**
   * Calls the API to delete the object.
   *
   * @throws \Apigee\Exceptions\ParameterException
   */
  public function delete() {
    if (!isset($this->id_field)) {
      throw new ParameterException('No object identifier was specified.');
    }
    $url = $this->base_url . '/' . rawurlencode($this->{$this->id_field});
    $this->client->delete($url);
    // Give the client a chance to throw a ResponseException in case we get a
    // non-200 response.
    $this->getResponse();
    // Presuming delete was successful, erase any org values here.
    $this->initValues();
  }

  protected function validateUri($url) {
    // Validate URL and make sure it is either http or https (no file:/// or
    // ftp:// allowed.)
    $uri = @parse_url($url);
    if (!$uri || ($uri['scheme'] != 'http' && $uri['scheme'] != 'https')) {
      return FALSE;
    }
    return TRUE;

  }

  protected static function to_snake_case($camelCase) {
    $snake = '';
    $chars = str_split($camelCase, 1);
    $snake .= strtolower(array_shift($chars));
    foreach ($chars as $char) {
      $char_lc = strtolower($char);
      if ($char != $char_lc) {
        $snake .= '_';
      }
      $snake .= $char_lc;
    }
    return $snake;
  }
  protected static function args_to_snake_case(&$args) {
    foreach (array_keys($args) as $i) {
      $args[$i] = self::to_snake_case($args[$i]);
    }
  }

}