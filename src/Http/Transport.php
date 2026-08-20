<?php

declare(strict_types=1);

namespace TimeFrontiers\Http;

interface Transport {

  public function send(TransportRequest $request):TransportResponse;
}
