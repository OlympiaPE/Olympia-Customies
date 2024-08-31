<?php
declare(strict_types=1);

namespace customiesdevs\customies\item\component;

final class RenderOffsetsComponent implements ItemComponent {

	private int $textureWidth;
	private int $textureHeight;
	private bool $handEquipped;
    private int $firstPersonDivider;
    private int $thirdPersonDivider;

	public function __construct(int $textureWidth, int $textureHeight, bool $handEquipped = false, int $firstPersonDivider = 1, int $thirdPersonDivider = 1) {
		$this->textureWidth = $textureWidth;
		$this->textureHeight = $textureHeight;
		$this->handEquipped = $handEquipped;
        $this->firstPersonDivider = $firstPersonDivider;
        $this->thirdPersonDivider = $thirdPersonDivider;
	}

	public function getName(): string {
		return "minecraft:render_offsets";
	}

	public function getValue(): array {
		$horizontal = ($this->handEquipped ? 0.075 : 0.1) / ($this->textureWidth / 16);
		$vertical = ($this->handEquipped ? 0.125 : 0.1) / ($this->textureHeight / 16);

		$perspectives = [
			"first_person" => [
				"scale" => [
                    round($horizontal / $this->firstPersonDivider, 2),
                    round($vertical / $this->firstPersonDivider, 2),
                    round($horizontal / $this->firstPersonDivider, 2)
                ]
			],
			"third_person" => [
                "scale" => [
                    round($horizontal / $this->thirdPersonDivider, 2),
                    round($vertical / $this->thirdPersonDivider, 2),
                    round($horizontal / $this->thirdPersonDivider, 2)
                ]
			]
		];
		return [
			"main_hand" => $perspectives,
			"off_hand" => $perspectives
		];
	}

	public function isProperty(): bool {
		return false;
	}
}