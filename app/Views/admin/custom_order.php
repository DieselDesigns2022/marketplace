<section>

    <h1>Custom Order #<?=(int)$order['id']?></h1>

    <div class="card">

        <p>
            <strong>Status:</strong>
            <?=H::e(ucwords(str_replace('_',' ',$order['status'])))?>
        </p>

        <p>
            <strong>Payment:</strong>
            <?=H::e(ucwords(str_replace('_',' ',$order['payment_status'])))?>
            · <?=H::money($order['total'])?>
        </p>

        <p>
            <strong>Buyer:</strong>
            <?=H::e($order['buyer_name'])?>
        </p>

        <p>
            <strong>Seller:</strong>
            <?=H::e($order['display_name'])?>
        </p>

        <?php if($order['manual_review_required']): ?>

            <p class="notice warning">
                <strong>Dispute / payment review:</strong>
                <?=H::e($order['manual_review_reason'])?>
            </p>

        <?php endif; ?>

    </div>

    <h2>Purchased Licenses</h2>

    <?php if(!$licenses): ?>

        <p class="muted">
            No stored license snapshot is available for this order.
        </p>

    <?php else: ?>

        <?php foreach($licenses as $license): ?>

            <article class="card" style="margin-bottom:12px;">

                <h3>
                    <?=H::e(
                        (string)(
                            $license['name']
                            ??$license['key']
                            ??'License'
                        )
                    )?>
                </h3>

                <?php if(
                    array_key_exists('included',$license) &&
                    $license['included']!==null
                ): ?>

                    <p>
                        <?=!empty($license['included'])
                            ?'Included with the Custom Design'
                            :'Additional license add-on'?>
                    </p>

                <?php endif; ?>

                <?php if(
                    array_key_exists('price',$license) &&
                    $license['price']!==null
                ): ?>

                    <p>
                        <strong>License price:</strong>
                        <?=H::money((float)$license['price'])?>
                    </p>

                <?php endif; ?>

                <?php if(
                    trim(
                        (string)(
                            $license['description']
                            ??''
                        )
                    )!==''
                ): ?>

                    <p>
                        <?=nl2br(
                            H::e(
                                (string)$license['description']
                            )
                        )?>
                    </p>

                <?php endif; ?>

            </article>

        <?php endforeach; ?>

    <?php endif; ?>

    <h2>Message Reports / Disputes</h2>

    <?php if(!$reports): ?>

        <p>No linked conversation reports.</p>

    <?php else: ?>

        <?php foreach($reports as $report): ?>

            <p>
                <a href="/admin/message-reports/<?=(int)$report['id']?>">
                    Report #<?=(int)$report['id']?>
                </a>
                —
                <?=H::e($report['reason'])?>
                (<?=H::e($report['status'])?>)
            </p>

        <?php endforeach; ?>

    <?php endif; ?>

    <h2>Submitted Brief</h2>

    <?php foreach($brief as $key=>$value): ?>

        <?php if($key==='answers' && is_array($value)): ?>

            <?php foreach($value as $answer): ?>

                <p>
                    <strong>
                        <?=H::e(
                            (string)(
                                $answer['question']
                                ??'Question'
                            )
                        )?>:
                    </strong>

                    <?=nl2br(
                        H::e(
                            (string)(
                                $answer['answer']
                                ??''
                            )
                        )
                    )?>
                </p>

            <?php endforeach; ?>

        <?php else: ?>

            <p>
                <strong>
                    <?=H::e(
                        ucwords(
                            str_replace(
                                '_',
                                ' ',
                                (string)$key
                            )
                        )
                    )?>:
                </strong>

                <?=nl2br(
                    H::e(
                        is_array($value)
                            ?json_encode(
                                $value,
                                JSON_UNESCAPED_SLASHES |
                                JSON_UNESCAPED_UNICODE
                            )
                            :(string)$value
                    )
                )?>
            </p>

        <?php endif; ?>

    <?php endforeach; ?>

    <h2>Delivery State</h2>

    <?php if(!$files): ?>

        <p class="muted">No protected files yet.</p>

    <?php else: ?>

        <?php foreach($files as $file): ?>

            <p>
                <strong>
                    <?=H::e(
                        ucfirst(
                            (string)$file['file_kind']
                        )
                    )?>:
                </strong>

                <?=H::e($file['original_name'])?>
            </p>

        <?php endforeach; ?>

    <?php endif; ?>

</section>
