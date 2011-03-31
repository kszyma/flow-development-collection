<?php
declare(ENCODING = 'utf-8');
namespace F3\FLOW3\Command;

/*                                                                        *
 * This script belongs to the FLOW3 framework.                            *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License as published by the *
 * Free Software Foundation, either version 3 of the License, or (at your *
 * option) any later version.                                             *
 *                                                                        *
 * This script is distributed in the hope that it will be useful, but     *
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-    *
 * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Lesser       *
 * General Public License for more details.                               *
 *                                                                        *
 * You should have received a copy of the GNU Lesser General Public       *
 * License along with the script.                                         *
 * If not, see http://www.gnu.org/licenses/lgpl.html                      *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

/**
 * Command controller for core commands
 *
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 * @scope singleton
 * @proxy disable
 */
class CoreCommandController extends \F3\FLOW3\MVC\Controller\CommandController {

	/**
	 * @var \F3\FLOW3\MVC\CLI\RequestBuilder
	 */
	protected $requestBuilder;

	/**
	 * @var \F3\FLOW3\MVC\Dispatcher
	 */
	protected $dispatcher;

	/**
	 * @var \F3\FLOW3\Core\Bootstrap
	 */
	protected $bootstrap;

	/**
	 * @var \F3\FLOW3\Cache\CacheManager
	 */
	protected $cacheManager;

	/**
	 * @var \F3\FLOW3\Object\Proxy\Compiler
	 */
	protected $proxyClassCompiler;

	/**
	 * @var \F3\FLOW3\AOP\Builder\ProxyClassBuilder
	 */
	protected $aopProxyClassBuilder;

	/**
	 * @var \F3\FLOW3\Object\DependencyInjection\ProxyClassBuilder
	 */
	protected $dependencyInjectionProxyClassBuilder;

	/**
	 * @param \F3\FLOW3\MVC\CLI\RequestBuilder $requestBuilder
	 * @return void
	 */
	public function injectRequestBuilder(\F3\FLOW3\MVC\CLI\RequestBuilder $requestBuilder) {
		$this->requestBuilder = $requestBuilder;
	}

	/**
	 * @param \F3\FLOW3\MVC\Dispatcher $dispatcher
	 * @return void
	 */
	public function injectDispatcher(\F3\FLOW3\MVC\Dispatcher $dispatcher) {
		$this->dispatcher = $dispatcher;
	}

	/**
	 * @param \F3\FLOW3\Core\Bootstrap $bootstrap
	 * @return void
	 */
	public function injectBootstrap(\F3\FLOW3\Core\Bootstrap $bootstrap) {
		$this->bootstrap = $bootstrap;
	}

	/**
	 * @param \F3\FLOW3\Cache\CacheManager $cacheManager
	 * @return void
	 */
	public function injectCacheManager(\F3\FLOW3\Cache\CacheManager $cacheManager) {
		$this->cacheManager = $cacheManager;
	}

	/**
	 * @param \F3\FLOW3\Object\Proxy\Compiler $proxyClassCompiler
	 * @return void
	 */
	public function injectProxyClassCompiler(\F3\FLOW3\Object\Proxy\Compiler $proxyClassCompiler) {
		$this->proxyClassCompiler = $proxyClassCompiler;
	}

	/**
	 * @param \F3\FLOW3\AOP\Builder\ProxyClassBuilder $aopProxyClassBuilder
	 * @return void
	 */
	public function injectAopProxyClassBuilder(\F3\FLOW3\AOP\Builder\ProxyClassBuilder $aopProxyClassBuilder) {
		$this->aopProxyClassBuilder = $aopProxyClassBuilder;
	}

	/**
	 * @param \F3\FLOW3\Object\DependencyInjection\ProxyClassBuilder $dependencyInjectionProxyClassBuilder
	 * @return void
	 */
	public function injectDependencyInjectionProxyClassBuilder(\F3\FLOW3\Object\DependencyInjection\ProxyClassBuilder $dependencyInjectionProxyClassBuilder) {
		$this->dependencyInjectionProxyClassBuilder = $dependencyInjectionProxyClassBuilder;
	}

	/**
	 * Help action
	 *
	 * @return string
	 */
	public function helpCommand() {
		return "Help is not implemented yet.";
	}

	/**
	 * Compiles all proxy classes
	 *
	 * @return void
	 */
	public function compileCommand($force = FALSE) {
		if ($force === FALSE) {
			$objectConfigurationCache = $this->cacheManager->getCache('FLOW3_Object_Configuration');
			if ($objectConfigurationCache->has('allCompiledCodeUpToDate')) {
				return;
			}
		}

		$this->proxyClassCompiler->injectClassesCache($this->cacheManager->getCache('FLOW3_Object_Classes'));

		$this->aopProxyClassBuilder->build();
		$this->dependencyInjectionProxyClassBuilder->build();

		$classCount = $this->proxyClassCompiler->compile();

		$objectConfigurationCache->set('allCompiledCodeUpToDate', TRUE, array($objectConfigurationCache->getClassTag()));
	}

	/**
	 * Action which provides an interactive shell
	 *
	 * @return void
	 */
	public function shellCommand() {
		$subProcess = FALSE;
		$pipes = array();

		$historyPathAndFilename = getenv('HOME') . '/.flow3_' . md5(FLOW3_PATH_ROOT);
		readline_read_history($historyPathAndFilename);
		readline_completion_function(array($this, 'autocomplete'));

		echo "FLOW3 Interactive Shell\n\n";

		while (true) {
			$commandLine = readline('FLOW3 > ');
			if ($commandLine == '') {
				echo "\n";
				break;
			}

			readline_add_history($commandLine);
			readline_write_history($historyPathAndFilename);

			$request = $this->requestBuilder->build($commandLine);
			$response = new \F3\FLOW3\MVC\CLI\Response();

			if ($request === FALSE || $request->getCommandIdentifier() === FALSE) {
				echo "Bad command\n";
				continue;
			}
			if ($this->bootstrap->isCompiletimeCommandController($request->getCommandIdentifier())) {
				$this->dispatcher->dispatch($request, $response);
				$response->send();
				if (is_resource($subProcess)) {
					$this->quitSubProcess($subProcess, $pipes);
				}
			} else {
				if (is_resource($subProcess)) {
					$subProcessStatus = proc_get_status($subProcess);
					if ($subProcessStatus['running'] === FALSE) {
						proc_close($subProcess);
					}
				};
				if (!is_resource($subProcess)) {
					list($subProcess, $pipes) = $this->launchSubProcess();
					if ($subProcess === FALSE) {
						echo "Failed launching the shell sub process for executing the runtime command.\n";
						continue;
					}
					$this->echoSubProcessResponse($pipes);
				}

				fwrite($pipes[0], "$commandLine\n");
				fflush($pipes[0]);
				$this->echoSubProcessResponse($pipes);
			 }
		}

		if (is_resource($subProcess)) {
			$this->quitSubProcess($subProcess, $pipes);
		}

		echo "Bye!\n";
	}

	/**
	 * Launch sub process
	 *
	 * @return array The new sub process and its STDIN, STDOUT, STDERR pipes – or FALSE if an error occurred.
	 */
	protected function launchSubProcess() {
		$systemCommand = 'FLOW3_ROOTPATH=' . FLOW3_PATH_ROOT . ' ' . 'FLOW3_CONTEXT=' . $this->bootstrap->getContext() . ' ' . PHP_BINDIR . '/php -c ' . php_ini_loaded_file() . ' ' . FLOW3_PATH_FLOW3 . 'Scripts/flow3' . ' --start-slave';
		$descriptorSpecification = array(array('pipe', 'r'), array('pipe', 'w'), array('pipe', 'a'));
		$subProcess = proc_open($systemCommand, $descriptorSpecification, $pipes);
		if (!is_resource($subProcess)) {
			throw new \RuntimeException('Could not execute sub process.');
		}

		$read = array($pipes[1]);
		$write = null;
		$except = null;
		$readTimeout = 30;

		stream_select($read, $write, $except, $readTimeout);

		$subProcessStatus = proc_get_status($subProcess);
		return ($subProcessStatus['running'] === TRUE) ? array($subProcess, $pipes) : FALSE;
	}

	/**
	 * Echoes the currently pending response from the sub process
	 *
	 * @param array $pipes
	 * @return void
	 */
	protected function echoSubProcessResponse(array $pipes) {
		while (feof($pipes[1]) === FALSE) {
			$responseLine = fgets($pipes[1]);
			if (trim($responseLine) === 'READY' || $responseLine === FALSE) {
				break;
			}
			echo($responseLine);
		}
	}

	/**
	 * Cleanly terminates the given sub process
	 *
	 * @param resource $subProcess The sub process to quite
	 * @param array $pipes The current STDIN, STDOUT and STDERR pipes
	 * @return void
	 */
	protected function quitSubProcess($subProcess, array $pipes) {
		fwrite($pipes[0], "QUIT\n");
		fclose($pipes[0]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		proc_close($subProcess);
	}

	/**
	 * To be implemented ...
	 */
	protected function autocomplete($partialCommand, $index) {
		return array();
	}
}
?>