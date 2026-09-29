<?php

declare(strict_types=1);

namespace App\Portal\Domain\Help;

use App\Portal\Domain\User\Grants;

/**
 * Una pregunta de la ayuda con su respuesta: "¿Cómo cierro el mes?" en
 * facturación, o "¿Cómo cambio a dónde me llegan los avisos?" para todos.
 *
 * Es de UNA aplicación (o "general", de toda la suite) y, si lleva roles, solo
 * la ve quien tiene alguno de ellos en esa aplicación: la ayuda de la
 * administración de fichajes no le sirve al empleado, y le haría buscar entre
 * respuestas que no son para él.
 *
 * El texto de la respuesta es texto plano con saltos de línea: lo pinta
 * @ampa/ui tal cual, sin HTML (que un fichero cargado desde fuera no pueda
 * meter nada en la página).
 */
final readonly class FaqEntry
{
    /** De toda la suite: la ve cualquiera que haya entrado. */
    public const string GENERAL = 'general';

    private const int MAX_ID = 100;
    private const int MAX_QUESTION = 300;
    private const int MAX_ANSWER = 10000;
    private const int MAX_KEYWORD = 80;
    private const int MAX_KEYWORDS = 50;
    private const int MAX_MANUAL = 100;

    /**
     * @param list<string> $roles    vacía: cualquiera con algún rol en la aplicación
     * @param list<string> $keywords palabras que no salen en la pregunta pero se buscan
     */
    private function __construct(
        private string $id,
        private string $application,
        private array $roles,
        private string $question,
        private string $answer,
        private array $keywords,
        private ?string $manual,
    ) {
    }

    /**
     * Solo la forma: que la aplicación y sus roles existan lo comprueba Faq con
     * el catálogo de la suite, que es quien los conoce (como con Grants).
     *
     * @param list<string> $roles
     * @param list<string> $keywords
     *
     * @throws FaqError
     */
    public static function of(
        string $id,
        string $application,
        array $roles,
        string $question,
        string $answer,
        array $keywords,
        ?string $manual,
    ): self {
        $id = trim($id);

        if ('' === $id) {
            throw new FaqError('Hay una pregunta sin identificador ("id").');
        }

        if (mb_strlen($id) > self::MAX_ID) {
            throw new FaqError(sprintf('El identificador "%s…" es demasiado largo (%d caracteres como mucho).', mb_substr($id, 0, 30), self::MAX_ID));
        }

        if (self::GENERAL === $application && [] !== $roles) {
            throw new FaqError(sprintf('"%s": las preguntas generales no llevan roles (las ve todo el mundo).', $id));
        }

        // Una palabra clave vacía no busca nada, pero tampoco estorba: se quita.
        $keywords = array_values(array_unique(array_filter(
            array_map(trim(...), $keywords),
            static fn (string $keyword): bool => '' !== $keyword,
        )));
        if (count($keywords) > self::MAX_KEYWORDS) {
            throw new FaqError(sprintf('"%s": demasiadas palabras clave (%d como mucho).', $id, self::MAX_KEYWORDS));
        }
        foreach ($keywords as $keyword) {
            self::text($id, sprintf('la palabra clave "%s…"', mb_substr($keyword, 0, 20)), $keyword, self::MAX_KEYWORD);
        }

        // Sin manual y con el manual vacío es lo mismo: una sola forma.
        $manual = null === $manual || '' === trim($manual) ? null : trim($manual);
        if (null !== $manual) {
            self::text($id, 'el manual', $manual, self::MAX_MANUAL);
        }

        return new self(
            $id,
            $application,
            array_values(array_unique($roles)),
            self::text($id, 'la pregunta', trim($question), self::MAX_QUESTION),
            // Sin trim por dentro: los saltos de línea de la respuesta son suyos.
            self::text($id, 'la respuesta', trim($answer), self::MAX_ANSWER),
            $keywords,
            $manual,
        );
    }

    /**
     * Desde la base de datos: ya se comprobó al cargarla, y leer la ayuda no
     * puede fallar porque un límite haya cambiado después.
     *
     * @param array{id: string, application: string, roles: list<string>, question: string, answer: string, keywords: list<string>, manual: string|null} $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['id'], $data['application'], $data['roles'], $data['question'], $data['answer'], $data['keywords'], $data['manual']);
    }

    /** @return array{id: string, application: string, roles: list<string>, question: string, answer: string, keywords: list<string>, manual: string|null} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'application' => $this->application,
            'roles' => $this->roles,
            'question' => $this->question,
            'answer' => $this->answer,
            'keywords' => $this->keywords,
            'manual' => $this->manual,
        ];
    }

    /**
     * Si la ve quien tiene estos permisos: las generales, todos; las de una
     * aplicación, quien entra en ella y, si la pregunta lleva roles, con uno
     * de ellos.
     */
    public function isVisibleWith(Grants $grants): bool
    {
        if (self::GENERAL === $this->application) {
            return true;
        }

        $held = $grants->rolesIn($this->application);

        if ([] === $held) {
            return false;
        }

        return [] === $this->roles || [] !== array_intersect($this->roles, $held);
    }

    public function getId(): string
    {
        return $this->id;
    }

    /** El código de la aplicación, o "general". */
    public function getApplication(): string
    {
        return $this->application;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getQuestion(): string
    {
        return $this->question;
    }

    public function getAnswer(): string
    {
        return $this->answer;
    }

    /** @return list<string> */
    public function getKeywords(): array
    {
        return $this->keywords;
    }

    /** El capítulo del manual donde se cuenta con más detalle ("06"), si lo hay. */
    public function getManual(): ?string
    {
        return $this->manual;
    }

    /**
     * @throws FaqError
     */
    private static function text(string $id, string $what, string $value, int $max): string
    {
        if ('' === $value) {
            throw new FaqError(sprintf('"%s": falta %s.', $id, $what));
        }

        if (mb_strlen($value) > $max) {
            throw new FaqError(sprintf('"%s": %s tiene más de %d caracteres.', $id, $what, $max));
        }

        return $value;
    }
}
