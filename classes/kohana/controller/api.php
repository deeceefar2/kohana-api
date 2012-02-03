<?php
abstract class Kohana_Controller_API extends OAuth2_Controller
{
	/**
	 * @var Object Request Payload
	 */
	protected $_request_payload = NULL;

	/**
	 * @var Object Response Payload
	 */
	protected $_response_payload = NULL;

	/**
	 * @var array Response Metadata
	 */
	protected $_response_metadata = array('error' => FALSE);

	/**
	 * @var array Response Links
	 */
	protected $_response_links = array();

	/**
	 * @var array Map of HTTP methods -> actions
	 */
	protected $_action_map = array
	(
		Http_Request::POST   => 'post',   // Typically Create..
		Http_Request::GET    => 'get',
		Http_Request::PUT    => 'put',    // Typically Update..
		Http_Request::DELETE => 'delete',
	);

	/**
	 * @var array List of HTTP methods which support body content
	 */
	protected $_methods_with_body_content = array
	(
		Http_Request::POST,
		Http_Request::PUT,
	);

	/**
	 * @var array List of HTTP methods which may be cached
	 */
	protected $_cacheable_methods = array
	(
		Http_Request::GET,
	);

	/**
	 * Formats allowed for output
	 */
	protected $_output_formats = array(
		'json',
		'xml',
	);

	public $oauth_actions = array();


	/**
	 * Creates a new controller instance. Each controller must be constructed with the request object that created it.
	 *
	 * @return	void
	 */
	public function __construct(Request $request, Response $response)
	{
		parent::__construct($request,$response);
	}

	public function before()
	{
		parent::before();


		// Override the method if needed.
		$this->request->method(Arr::get(
			$_SERVER,
			'HTTP_X_HTTP_METHOD_OVERRIDE',
			$this->request->method()
		));

		// Is that a valid method?
		if ( ! isset($this->_action_map[$this->request->method()]))
		{
			// TODO .. add to the if (maybe??) .. method_exists($this, 'action_'.$this->request->method())
			throw new Http_Exception_405('The :method method is not supported. Supported methods are :allowed_methods', array(
				':method'          => $method,
				':allowed_methods' => implode(', ', array_keys($this->_action_map)),
			));
		}

		// Are we be expecting body content as part of the request?
		if (in_array($this->request->method(), $this->_methods_with_body_content) && $this->request->body() != '')
		{
			try
			{
				$this->_request_payload = json_decode($this->request->body(), TRUE);

				if ( ! is_array($this->_request_payload) AND ! is_object($this->_request_payload))
					throw new Http_Exception_400('Invalid json supplied. \':json\'', array(
						':json' => $this->request->body(),
					));
			}
			catch (Exception $e)
			{
				throw new Http_Exception_400('Invalid json supplied. \':json\'', array(
					':json' => $this->request->body(),
				));
			}
		}
	}

	/**
	 * Execute the API call..
	 */
	public function action_index()
	{
		// Get the basic verb based action..
		$action = $this->_action_map[$this->request->method()];

		// If this is a custom action, lets make sure we use it.
		if ($this->request->param('custom', FALSE) !== FALSE)
		{
			$action .= '_'.$this->request->param('custom');
		}

		// If we are acting on a collection, append _collection to the action name.
		if ($this->request->param('id', FALSE) === FALSE)
		{
			$action .= '_collection';
		}

		// Execute the request
		if (method_exists($this, $action))
		{
			if(array_key_exists($action, $this->oauth_actions))
				$this->_oauth_verify_token();
			$this->{$action}();
		}
		else
		{
			/**
			 * @todo .. HTTP_Exception_405 is more approperiate, sometimes.
			 * Need to figure out a way to decide which to send...
			 */
			throw new HTTP_Exception_404('The requested URL :uri was not found on this server.', array(
				':uri' => $this->request->uri()
			));
		}
	}

	public function after()
	{
		try
		{
			$view = View::factory('api');

			$view->set_data_tag('payload');
			$view->set_global_tag('metadata');

			// Should we prevent this request from being cached?
			if ( ! in_array($this->request->method(), $this->_cacheable_methods))
			{
				$this->response->headers('cache-control', 'no-cache, no-store, max-age=0, must-revalidate');
			}

			$this->response->headers('Content-Type', File::mime_by_ext($this->_response_format()));

			// Load the template
			$view->output_format($this->_response_format());

			$view->set_global($this->_response_metadata);
			$view->set($this->_response_payload);

			$this->response->body($view->render());

		}
		catch (Exception $e)
		{
			throw $e;
		}

		parent::after();
	}

	protected function _response_format() {

		if ($this->request->param('format', FALSE) !== FALSE)
		{
			return $this->request->param('format');
		} elseif ($this->request->param('extension', FALSE) !== FALSE) {
			return $this->request->param('extension');
		}
		foreach($this->request->accept_type() as $format=>$priority) {
			if($format == '*/*')
				break;
			$exts = File::exts_by_mime($format);
			if(is_array($exts)) {
				foreach($exts as $ext) {
					if(in_array($ext,$this->_output_formats)) {
						return $ext;
					}
				}
			} else {
				if(in_array($exts,$this->_output_formats)) {
					return $exts;
				}
			}
		}
		return 'json';
	}
}