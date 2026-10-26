<?php
$allReferrals = array_merge(
    $buyerRefs ?? [],
    $refs ?? []
);

usort(
    $allReferrals,
    static fn(array $a, array $b): int =>
        ((int)($b['id'] ?? 0)) <=> ((int)($a['id'] ?? 0))
);

$referralLink = H::baseUrl()
    . '/register?ref='
    . rawurlencode($storeSlug ?: $referralCode);
?>

<section class="card" style="margin-bottom:1.5rem">
    <h1>Your Creative Moth Referral Link</h1>

    <p>
        <code><?= H::e($referralLink) ?></code>
    </p>

    <p>
        <strong>One link handles both buyer and seller referrals.</strong>
        Everyone you refer should create their Creative Moth account through
        this link. Their referral type is determined by what they do after
        signing up.
    </p>
</section>


<div class="cards" style="margin-bottom:1.5rem">

    <section class="card">
        <h2>Buyer Referrals</h2>

        <p>
            Earn <strong>$5.00 in Advertising Credit</strong> when a
            brand-new buyer you refer spends at least
            <strong>$8 in qualifying purchases</strong>.
        </p>

        <ul>
            <li>
                They must create their account through your referral link.
            </li>

            <li>
                They have <strong>30 days from account creation</strong>
                to reach $8 in qualifying purchases.
            </li>

            <li>
                The $8 may be spread across multiple qualifying orders.
            </li>

            <li>
                Only actual paid purchase amounts count.
                Marketplace credits, prize credits, failed orders,
                cancelled orders, refunded or partially refunded orders,
                unpaid orders, and unresolved manual-review orders do not count.
            </li>

            <li>
                Once the $8 requirement is reached, you receive
                <strong>$5 Advertising Credit</strong>.
            </li>

            <li>
                The referred buyer does not receive a referral reward.
            </li>
        </ul>
    </section>


    <section class="card">
        <h2>Seller Referrals</h2>

        <p>
            If the person you referred intends to sell on Creative Moth,
            they should <strong>apply to become a seller immediately after
            creating their account through your referral link.</strong>
        </p>

        <ul>
            <li>
                Once their seller application is submitted, the referral
                automatically changes from Buyer to <strong>Seller</strong>.
            </li>

            <li>
                Their buyer-reward eligibility ends immediately when they
                apply to sell.
            </li>

            <li>
                If you are an approved seller when the seller referral
                qualifies, you earn <strong>1% of that store's net seller
                earnings</strong>, funded by Creative Moth.
            </li>

            <li>
                The commission does not reduce the referred seller's payout.
            </li>

            <li>
                If you are not an approved seller when the seller referral
                qualifies, the existing <strong>$5.00 store-credit</strong>
                reward applies instead.
            </li>

            <li>
                A disabled referred store temporarily pauses new commission.
                If the referred seller goes 90 consecutive days without a
                qualifying sale, the commission relationship permanently ends.
            </li>
        </ul>
    </section>

</div>


<section class="card" style="margin-bottom:1.5rem">
    <h2>Important Referral Rules</h2>

    <p>
        <strong>A single referred person cannot earn you both a buyer reward
        and a seller referral reward.</strong>
    </p>

    <p>
        If you are referring someone specifically because they plan to sell,
        tell them to sign up through your referral link and
        <strong>apply to sell immediately</strong>.
    </p>

    <p>
        If they first reach the buyer requirement and your $5 Advertising
        Credit is earned, applying to become a seller afterward will
        <strong>not</strong> create an additional seller referral reward.
    </p>

    <p>
        Please make sure your referral follows the correct process.
        <strong>Creative Moth cannot manually reassign referrals, convert an
        already-earned buyer referral into a seller referral, or issue a
        missed referral reward when the required referral process was not
        followed.</strong>
    </p>
</section>


<section class="card" style="margin-bottom:1.5rem">
    <h2>Your Referrals</h2>

    <div class="responsive-table">
        <table>
            <thead>
                <tr>
                    <th>Referral</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Progress / Reward</th>
                </tr>
            </thead>

            <tbody>

            <?php if (!$allReferrals): ?>
                <tr>
                    <td colspan="4">You have not referred anyone yet.</td>
                </tr>
            <?php endif; ?>


            <?php foreach ($allReferrals as $referral): ?>

                <?php
                $isSeller =
                    !empty($referral['seller_intent']) ||
                    (($referral['referral_type'] ?? '') === 'seller');

                $name =
                    $referral['referred_store']
                    ?? $referral['referred_name']
                    ?? 'Referred member';
                ?>

                <tr>
                    <td>
                        <strong><?= H::e($name) ?></strong>
                    </td>


                    <td>
                        <strong>
                            <?= $isSeller ? 'Seller' : 'Buyer' ?>
                        </strong>
                    </td>


                    <td>
                        <?php if ($isSeller): ?>

                            <?php if (!empty($referral['commission_ended_at'])): ?>

                                Permanently stopped

                                <br>

                                <?= H::e($referral['commission_ended_at']) ?>

                                <?php if (!empty($referral['commission_end_reason'])): ?>
                                    ·
                                    <?= H::e(
                                        ucwords(
                                            str_replace(
                                                'store_',
                                                'store ',
                                                $referral['commission_end_reason']
                                            )
                                        )
                                    ) ?>
                                <?php endif; ?>


                            <?php elseif (($referral['seller_status'] ?? '') === 'rejected'): ?>

                                Seller reward unavailable


                            <?php elseif (!empty($referral['seller_rewarded_at'])): ?>

                                Qualified


                            <?php else: ?>

                                <?= H::e(
                                    ucfirst(
                                        $referral['seller_status'] ?? 'pending'
                                    )
                                ) ?>

                            <?php endif; ?>


                        <?php else: ?>

                            <?php if (!empty($referral['buyer_rewarded_at'])): ?>

                                Qualified


                            <?php elseif (($referral['buyer_status'] ?? '') === 'rejected'): ?>

                                Expired / unavailable


                            <?php else: ?>

                                Pending

                            <?php endif; ?>

                        <?php endif; ?>
                    </td>


                    <td>
                        <?php if ($isSeller): ?>

                            <?php if (
                                ($referral['seller_reward_type'] ?? '')
                                === 'lifetime_commission'
                            ): ?>

                                <strong>1% Lifetime Commission</strong>

                                <br>

                                Pending
                                <?= H::money(
                                    ((int)(
                                        $referral['pending_commission_cents']
                                        ?? 0
                                    )) / 100
                                ) ?>

                                · Paid
                                <?= H::money(
                                    ((int)(
                                        $referral['paid_commission_cents']
                                        ?? 0
                                    )) / 100
                                ) ?>

                                <br>

                                Total
                                <?= H::money(
                                    ((int)(
                                        $referral['lifetime_commission_cents']
                                        ?? 0
                                    )) / 100
                                ) ?>


                            <?php elseif (
                                ($referral['seller_reward_type'] ?? '')
                                === 'store_credit'
                            ): ?>

                                <strong>$5.00 Store Credit</strong>

                                <?php if (!empty($referral['seller_rewarded_at'])): ?>
                                    <br>Awarded
                                <?php endif; ?>


                            <?php elseif (
                                ($referral['seller_status'] ?? '')
                                === 'rejected'
                            ): ?>

                                No seller reward


                            <?php else: ?>

                                Waiting for qualifying seller sale

                            <?php endif; ?>


                        <?php else: ?>

                            <?php
                            $buyerSpend =
                                (float)(
                                    $referral['buyer_qualifying_spend']
                                    ?? 0
                                );
                            ?>

                            <?= H::money($buyerSpend) ?> / $8.00

                            <?php if (!empty($referral['buyer_rewarded_at'])): ?>

                                <br>
                                <strong>
                                    $5 Advertising Credit awarded
                                </strong>


                            <?php elseif (
                                ($referral['buyer_status'] ?? '')
                                === 'pending'
                            ): ?>

                                <br>
                                Deadline:
                                <?= H::e(
                                    !empty($referral['buyer_expires_at'])
                                        ? date(
                                            'M j, Y',
                                            strtotime(
                                                $referral['buyer_expires_at']
                                            )
                                        )
                                        : '—'
                                ) ?>

                            <?php endif; ?>

                        <?php endif; ?>
                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>
        </table>
    </div>
</section>


<section class="card">
    <h2>Recent Seller Referral Payouts</h2>

    <div class="responsive-table">
        <table>
            <thead>
                <tr>
                    <th>Period</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Completed / Problem</th>
                </tr>
            </thead>

            <tbody>

            <?php if (empty($payouts)): ?>
                <tr>
                    <td colspan="4">No monthly payouts yet.</td>
                </tr>
            <?php endif; ?>


            <?php foreach (($payouts ?? []) as $payout): ?>
                <tr>
                    <td>
                        <?= H::e($payout['period_start']) ?>
                        –
                        <?= H::e($payout['period_end']) ?>
                    </td>

                    <td>
                        <?= H::money(
                            ((int)($payout['amount_cents'] ?? 0)) / 100
                        ) ?>
                    </td>

                    <td>
                        <?= H::e(
                            ucwords(
                                str_replace(
                                    '_',
                                    ' ',
                                    $payout['status'] ?? ''
                                )
                            )
                        ) ?>
                    </td>

                    <td>
                        <?= H::e(
                            $payout['succeeded_at']
                            ?? (
                                ($payout['status'] ?? '') === 'not_ready'
                                    ? 'Your payout account is not ready; earnings remain unpaid.'
                                    : (
                                        ($payout['status'] ?? '') === 'failed'
                                            ? 'Transfer failed; earnings remain unpaid for retry.'
                                            : '—'
                                    )
                            )
                        ) ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            </tbody>
        </table>
    </div>
</section>
