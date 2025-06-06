<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_Versions\Tests\Controller;

use OCA\Files_Versions\Controller\PreviewController;
use OCA\Files_Versions\Versions\IVersionManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\IPreview;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Preview\IMimeIconProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class PreviewControllerTest extends TestCase {
	private IRootFolder&MockObject $rootFolder;
	private string $userId;
	private IPreview&MockObject $previewManager;
	private IUserSession&MockObject $userSession;
	private IVersionManager&MockObject $versionManager;

	private IMimeIconProvider&MockObject $mimeIconProvider;
	private PreviewController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->userId = 'user';
		$user = $this->createMock(IUser::class);
		$user->expects($this->any())
			->method('getUID')
			->willReturn($this->userId);
		$this->previewManager = $this->createMock(IPreview::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->userSession->expects($this->any())
			->method('getUser')
			->willReturn($user);
		$this->versionManager = $this->createMock(IVersionManager::class);
		$this->mimeIconProvider = $this->createMock(IMimeIconProvider::class);

		$this->controller = new PreviewController(
			'files_versions',
			$this->createMock(IRequest::class),
			$this->rootFolder,
			$this->userSession,
			$this->versionManager,
			$this->previewManager,
			$this->mimeIconProvider,
		);
	}

	public function testInvalidFile(): void {
		$res = $this->controller->getPreview('');
		$expected = new DataResponse([], Http::STATUS_BAD_REQUEST);

		$this->assertEquals($expected, $res);
	}

	public function testInvalidWidth(): void {
		$res = $this->controller->getPreview('file', 0);
		$expected = new DataResponse([], Http::STATUS_BAD_REQUEST);

		$this->assertEquals($expected, $res);
	}

	public function testInvalidHeight(): void {
		$res = $this->controller->getPreview('file', 10, 0);
		$expected = new DataResponse([], Http::STATUS_BAD_REQUEST);

		$this->assertEquals($expected, $res);
	}

	public function testInvalidVersion(): void {
		$res = $this->controller->getPreview('file', 10, 0);
		$expected = new DataResponse([], Http::STATUS_BAD_REQUEST);

		$this->assertEquals($expected, $res);
	}

	public function testValidPreview(): void {
		$userFolder = $this->createMock(Folder::class);
		$userRoot = $this->createMock(Folder::class);

		$this->rootFolder->method('getUserFolder')
			->with($this->userId)
			->willReturn($userFolder);
		$userFolder->method('getParent')
			->willReturn($userRoot);

		$sourceFile = $this->createMock(File::class);
		$userFolder->method('get')
			->with('file')
			->willReturn($sourceFile);

		$file = $this->createMock(File::class);
		$file->method('getMimetype')
			->willReturn('myMime');

		$this->versionManager->method('getVersionFile')
			->willReturn($file);

		$preview = $this->createMock(ISimpleFile::class);
		$preview->method('getName')->willReturn('name');
		$preview->method('getMTime')->willReturn(42);
		$this->previewManager->method('getPreview')
			->with($this->equalTo($file), 10, 10, true, IPreview::MODE_FILL, 'myMime')
			->willReturn($preview);
		$preview->method('getMimeType')
			->willReturn('previewMime');

		$res = $this->controller->getPreview('file', 10, 10, '42');

		$this->assertEquals('previewMime', $res->getHeaders()['Content-Type']);
		$this->assertEquals(Http::STATUS_OK, $res->getStatus());
		$this->assertEquals($preview, $this->invokePrivate($res, 'file'));
	}

	public function testVersionNotFound(): void {
		$userFolder = $this->createMock(Folder::class);
		$userRoot = $this->createMock(Folder::class);

		$this->rootFolder->method('getUserFolder')
			->with($this->userId)
			->willReturn($userFolder);
		$userFolder->method('getParent')
			->willReturn($userRoot);

		$sourceFile = $this->createMock(File::class);
		$userFolder->method('get')
			->with('file')
			->willReturn($sourceFile);

		$this->versionManager->method('getVersionFile')
			->willThrowException(new NotFoundException());

		$res = $this->controller->getPreview('file', 10, 10, '42');
		$expected = new DataResponse([], Http::STATUS_NOT_FOUND);

		$this->assertEquals($expected, $res);
	}
}
