<?php

namespace Omnimeet\Tests;

use Omnimeet\Bridge\Symfony\OmnimeetBundle;
use Omnimeet\Direct\DirectGatewayFactory;
use Omnimeet\Jitsi\JitsiGatewayFactory;
use Omnimeet\Registry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Omnimeet outside Symfony: the harness's bare script (docker/harness/bin/bare)
 * run in a PHP process of its own - this one has loaded the bundle's tests -
 * builds the registry by hand, holds a meeting on each gateway installed
 * (a whole handshake on omnimeet/direct, a frame and its token on
 * omnimeet/jitsi), and reports every class and file PHP loaded on the way.
 * None may be a framework's - and, since no gateway calls anything, none
 * may be Symfony's at all.
 */
final class BareTest extends TestCase
{
    private const FRAMEWORK = '~^(?:Symfony\\\\Component\\\\(?:DependencyInjection|Config|HttpKernel|HttpFoundation)|Symfony\\\\Bundle|Doctrine|Twig)\\\\~';
    private const FRAMEWORK_FILES = '~/vendor/(?:symfony/(?:dependency-injection|config|http-kernel|http-foundation|[a-z-]*bundle)|doctrine|twig)/~';

    public function testTheRegistryIsBuiltByHandAndNoClassOfAFrameworkIsLoaded(): void
    {
        [$status, $report] = self::php([__DIR__.'/../docker/harness/bin/bare', '--json']);

        self::assertSame(0, $status);
        self::assertContains(Registry::class, self::strings($report, 'symbols'), 'the registry was built there');
        self::assertSame([], self::framework($report), 'no class nor file of a framework');
        self::assertSame([], self::grep('~^Symfony\\\\(?!Polyfill)~', self::strings($report, 'symbols')), 'nothing of Symfony at all: no gateway calls anything');
        $plugins = require __DIR__.'/../docker/harness/plugins.php';
        self::assertIsArray($plugins);
        $installed = array_values(array_filter(array_column($plugins, 1), static fn (mixed $factory): bool => \is_string($factory) && class_exists($factory)));
        foreach ($installed as $factory) {
            self::assertContains($factory, self::strings($report, 'symbols'), 'every gateway package installed, its factory built');
        }
        $gateways = self::at($report, 'gateways');
        self::assertIsArray($gateways);
        self::assertCount(\count($installed), $gateways);
    }

    public function testAMeetingIsHeldFromBrowserToBrowserWithNoClassOfAFrameworkLoaded(): void
    {
        if (!class_exists(DirectGatewayFactory::class)) {
            self::markTestSkipped('omnimeet/direct is not installed.');
        }
        [$status, $report] = self::php([__DIR__.'/../docker/harness/bin/bare', '--json'], ['TURN_SECRET' => '', 'TURN_URLS' => '', 'STUN_URLS' => '']);

        self::assertSame(0, $status);
        self::assertSame(['open', 'join', 'close', 'fetch'], self::at($report, 'gateways', 'direct', 'does'), 'no callbacks: nobody to send any');
        self::assertSame([2, false, true, false], [self::at($report, 'gateways', 'direct', 'maxParticipants'), self::at($report, 'gateways', 'direct', 'thirdParty'), self::at($report, 'gateways', 'direct', 'endToEnd'), self::at($report, 'gateways', 'direct', 'recording')]);
        self::assertSame(['kind' => 'engine', 'engine' => 'direct', 'script' => 'visio.js', 'role' => 'host', 'ice_servers' => 0, 'third_party' => false], self::at($report, 'direct', 'access'), 'the engine\'s file is in the package; no relay configured, no STUN of someone else\'s');
        self::assertSame('guest', self::at($report, 'direct', 'guest_role'));
        self::assertSame(['hello'], self::at($report, 'direct', 'host_heard'), 'one does not hear oneself');
        self::assertSame(['offer', 'candidate'], self::at($report, 'direct', 'guest_heard'), 'in the order they were sent');
        self::assertTrue(self::at($report, 'direct', 'connects'));
        self::assertSame(['live', ['u2', 'u1']], [self::at($report, 'direct', 'status'), self::at($report, 'direct', 'present')]);
        self::assertSame(['ended', 0], [self::at($report, 'direct', 'closed'), self::at($report, 'direct', 'left_in_store')], 'closing purges the handshake');
        self::assertSame([], self::framework($report), 'no class nor file of a framework');
    }

    public function testAFrameAndItsTokenAreMadeWithNoClassOfAFrameworkLoaded(): void
    {
        if (!class_exists(JitsiGatewayFactory::class)) {
            self::markTestSkipped('omnimeet/jitsi is not installed.');
        }
        [$status, $report] = self::php([__DIR__.'/../docker/harness/bin/bare', '--json'], ['JITSI_DOMAIN' => '']);

        self::assertSame(0, $status);
        self::assertTrue(self::at($report, 'gateways', 'jitsi', 'example'), 'no instance in the environment: the example one, which nothing calls');
        self::assertSame(['open', 'join'], self::at($report, 'gateways', 'jitsi', 'does'), 'what Jitsi\'s documentation does not show is not done');
        self::assertTrue(self::at($report, 'gateways', 'jitsi', 'thirdParty'));
        $reference = self::at($report, 'jitsi', 'reference');
        self::assertIsString($reference);
        self::assertMatchesRegularExpression('~^[0-9a-f]{32}$~', $reference);
        self::assertTrue(self::at($report, 'jitsi', 'same_again'), 'the same key, the same room');
        self::assertSame('https://meet.example.org/'.$reference, self::at($report, 'jitsi', 'access', 'url'));
        self::assertSame(['frame', 'jitsi', 'jitsi.js', 'https://meet.example.org/external_api.js', ['https://meet.example.org'], true], [self::at($report, 'jitsi', 'access', 'kind'), self::at($report, 'jitsi', 'access', 'engine'), self::at($report, 'jitsi', 'access', 'script'), self::at($report, 'jitsi', 'access', 'api'), self::at($report, 'jitsi', 'access', 'origins'), self::at($report, 'jitsi', 'access', 'third_party')]);
        self::assertSame(['room' => $reference, 'user' => ['id' => 'u1', 'name' => 'Dr Claire Tilleul'], 'expires_after_the_end' => true], self::at($report, 'jitsi', 'token'));
        self::assertSame([], self::framework($report), 'no class nor file of a framework');
    }

    /** The check is not blind: the same report, once the bundle is loaded, names the framework. */
    public function testTheBundleDoesLoadTheFramework(): void
    {
        if (!class_exists(AbstractBundle::class)) {
            self::markTestSkipped('symfony/http-kernel is not installed.');
        }
        [$status, $report] = self::php(['-r', 'require getenv("OMNIMEET_AUTOLOAD"); class_exists($argv[1]) || exit(2); echo json_encode(["symbols" => [...get_declared_classes(), ...get_declared_interfaces(), ...get_declared_traits()], "files" => get_included_files()]);', '--', OmnimeetBundle::class]);

        self::assertSame(0, $status);
        $framework = self::framework($report);
        self::assertContains(AbstractBundle::class, $framework);
        self::assertNotEmpty(self::grep('~/symfony/http-kernel/~', $framework));
    }

    /**
     * @param array<mixed> $report
     *
     * @return list<string> the classes, interfaces, traits and files of a framework among those loaded
     */
    private static function framework(array $report): array
    {
        return [...self::grep(self::FRAMEWORK, self::strings($report, 'symbols')), ...self::grep(self::FRAMEWORK_FILES, self::strings($report, 'files'))];
    }

    /**
     * A value of the report, down its keys.
     *
     * @param array<mixed> $report
     */
    private static function at(array $report, string ...$keys): mixed
    {
        $value = $report;
        foreach ($keys as $key) {
            self::assertIsArray($value);
            self::assertArrayHasKey($key, $value);
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * A list of names in the report: "symbols", "files".
     *
     * @param array<mixed> $report
     *
     * @return list<string>
     */
    private static function strings(array $report, string $key): array
    {
        $value = self::at($report, $key);
        self::assertIsList($value);
        $list = [];
        foreach ($value as $one) {
            self::assertIsString($one);
            $list[] = $one;
        }

        return $list;
    }

    /**
     * @param list<string> $list
     *
     * @return list<string> those matching
     */
    private static function grep(string $pattern, array $list): array
    {
        return array_values(array_filter($list, static fn (string $one): bool => 1 === preg_match($pattern, $one)));
    }

    /**
     * Runs PHP apart, on the autoloader of this run.
     *
     * @param list<string>          $arguments
     * @param array<string, string> $env
     *
     * @return array{int, array<mixed>} the exit status, the JSON printed
     */
    private static function php(array $arguments, array $env = []): array
    {
        $autoload = \dirname((string) (new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName(), 2).'/autoload.php';
        $process = proc_open([\PHP_BINARY, ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env + ['OMNIMEET_AUTOLOAD' => $autoload] + getenv());
        self::assertIsResource($process);
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        $status = proc_close($process);
        $report = json_decode($out, true);
        self::assertIsArray($report, 'PHP exited '.$status.': '.$err.$out);

        return [$status, $report];
    }
}
