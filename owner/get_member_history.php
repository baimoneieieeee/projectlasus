<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'owner') {
    die("Access Denied");
}

if (!isset($_GET['m_id'])) {
    die("No member ID provided");
}

$m_id = $conn->real_escape_string($_GET['m_id']);
$member = $conn->query("SELECT * FROM member WHERE m_id = '$m_id'")->fetch_assoc();
$shop = $conn->query("SELECT * FROM shop LIMIT 1")->fetch_assoc();
$sql_orders = "SELECT * FROM orders WHERE m_id = '$m_id' ORDER BY order_date DESC";
$res_orders = $conn->query($sql_orders);

if (!$member) {
    die("Member not found");
}
?>

<div class="row g-4">
    <div class="col-md-4 no-print">
        <div class="card border-0 bg-light rounded-4 p-4 h-100 shadow-sm">
            <div class="text-center mb-4">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($member['m_name']) ?>&size=100&background=random" class="rounded-circle mb-3 shadow-sm border p-1 bg-white">
                <h4 class="fw-bold mb-1"><?= htmlspecialchars($member['m_name'] . ' ' . $member['m_lastname']) ?></h4>
                <p class="text-muted small mb-0"><i class="fa fa-phone me-1 text-primary"></i> <?= $member['m_tel'] ?></p>
            </div>
            <div class="border-top pt-3">
                <div class="small fw-bold text-primary mb-2 uppercase">ข้อมูลติดต่อ</div>
                <div class="p-3 bg-white rounded-3 small text-dark border">
                    <i class="fa fa-map-marker-alt text-danger me-1"></i> <?= nl2br(htmlspecialchars($member['m_address'])) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <h5 class="fw-bold mb-3 no-print"><i class="fa fa-file-invoice-dollar me-2 text-primary"></i> รายการใบเสร็จ/บิลทั้งหมด</h5>
        <div class="accordion accordion-flush rounded-4 overflow-hidden border" id="orderAccordion">
            <?php if($res_orders->num_rows > 0): ?>
                <?php while($o = $res_orders->fetch_assoc()): 
                    $o_id = $o['order_id'];
                    $is_pkg = (strpos($o_id, 'PKG') === 0);
                    $s_text = [0=>'รอรับผ้า', 1=>'รอจ่ายเงิน', 2=>'กำลังซักรีด', 3=>'เตรียมส่งคืน', 4=>'สำเร็จ'];
                    $s_color = [0=>'bg-secondary', 1=>'bg-warning text-dark', 2=>'bg-info', 3=>'bg-primary', 4=>'bg-success'];
                ?>
                <div class="accordion-item">
                    <h2 class="accordion-header no-print">
                        <button class="accordion-button collapsed px-4 py-3" type="button" data-bs-toggle="collapse" data-bs-target="#order-<?= $o_id ?>">
                            <div class="d-flex justify-content-between align-items-center w-100 me-3">
                                <div>
                                    <span class="fw-bold text-dark">#<?= $o_id ?></span>
                                    <span class="ms-2 badge <?= $s_color[$o['order_status']] ?> smaller"><?= $s_text[$o['order_status']] ?></span>
                                    <div class="smaller text-muted mt-1"><?= date('d M Y H:i', strtotime($o['order_date'])) ?></div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold text-primary">฿<?= number_format($o['order_sum'], 2) ?></div>
                                </div>
                            </div>
                        </button>
                    </h2>
                    <div id="order-<?= $o_id ?>" class="accordion-collapse collapse" data-bs-parent="#orderAccordion">
                        <div class="accordion-body p-4 bg-light bg-opacity-50">
                            
                            <!-- 🏛️ ใบเสร็จแบบทางการ (Official Receipt) -->
                            <div id="print-area-<?= $o_id ?>" class="bg-white mx-auto shadow-sm" style="max-width: 210mm; min-height: 100px; padding: 40px; border: 1px solid #ddd;">
                                <div class="row mb-4">
                                    <div class="col-7">
                                        <h4 class="fw-bold text-primary mb-1"><?= htmlspecialchars($shop['shop_name1']) ?></h4>
                                        <div class="small text-muted mb-0"><?= htmlspecialchars($shop['shop_address']) ?></div>
                                        <div class="small text-muted">โทร: <?= $shop['shop_tel'] ?> | อีเมล: <?= $shop['shop_mail'] ?></div>
                                    </div>
                                    <div class="col-5 text-end">
                                        <h3 class="fw-bold text-secondary mb-0">ใบเสร็จรับเงิน</h3>
                                        <div class="fw-bold text-dark">OFFICIAL RECEIPT</div>
                                        <div class="small mt-2">เลขที่: <b>#<?= $o_id ?></b></div>
                                        <div class="small">วันที่: <?= date('d/m/Y', strtotime($o['order_date'])) ?></div>
                                    </div>
                                </div>

                                <div class="p-3 bg-light rounded-3 mb-4 border">
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="info-label text-muted x-small fw-bold">ลูกค้า / Customer:</div>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($member['m_name'] . ' ' . $member['m_lastname']) ?></div>
                                            <div class="small text-muted"><?= $member['m_tel'] ?></div>
                                        </div>
                                        <div class="col-6 text-end">
                                            <div class="info-label text-muted x-small fw-bold">สถานะออเดอร์:</div>
                                            <div class="fw-bold text-success"><?= $s_text[$o['order_status']] ?></div>
                                        </div>
                                    </div>
                                </div>

                                <table class="table table-bordered mb-4">
                                    <thead class="table-light">
                                        <tr class="small fw-bold">
                                            <th class="py-2 text-center" style="width: 50px;">ลำดับ</th>
                                            <th class="py-2">รายการบริการ / Description</th>
                                            <th class="py-2 text-center" style="width: 100px;">จำนวน</th>
                                            <th class="py-2 text-end" style="width: 150px;">รวมเงิน (บาท)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="small">
                                        <?php 
                                        $row_idx = 1;
                                        $order_id_safe = $conn->real_escape_string($o_id);

                                        if ($is_pkg) {
                                            // --- 📦 กรณีสมัครแพ็กเกจ: แสดงรายการเดียว ---
                                            $pkg_name = trim(str_replace('สมัครแพ็กเกจ:', '', explode("\n", $o['order_note'])[0]));
                                            $pkg_price = (float)$o['order_sum'] - (float)$o['order_shipping_fee'] - (float)$o['order_extra_cash'];
                                            ?>
                                            <tr>
                                                <td class="text-center"><?= $row_idx++ ?></td>
                                                <td class="fw-bold">ค่าสมัครแพ็กเกจ: <?= htmlspecialchars($pkg_name) ?></td>
                                                <td class="text-center">1</td>
                                                <td class="text-end"><?= number_format($pkg_price, 2) ?></td>
                                            </tr>
                                            <?php
                                        } else {
                                            // --- 🧺 กรณีออเดอร์ซักผ้าปกติ: ดึงรายการจาก Note และจับคู่ราคา ---
                                            $order_note = $o['order_note'];
                                            
                                            // 🛠️ ปรับปรุง Regex: ใช้ดึงแบบทีละรายการ (.+?) และหยุดที่วงเล็บ
                                            preg_match_all('/(.+?)\s*\((\d+)\)/u', $order_note, $matches, PREG_SET_ORDER);
                                            
                                            if (!empty($matches)) {
                                                foreach ($matches as $m) {
                                                    $name = trim($m[1]);
                                                    // ถ้าชื่อรายการยาวเกินไป (เกิดจาก Regex จับกินยอดก่อนหน้า) ให้ตัดเอาเฉพาะส่วนท้าย
                                                    if (preg_match_all('/\)\s*(.*)$/u', $name, $sub_m)) {
                                                        $name = trim(end($sub_m[1]));
                                                    }
                                                    $qty = (int)$m[2];
                                                    
                                                    // ค้นหาราคาจากฐานข้อมูล
                                                    $srv_res = $conn->query("SELECT service_price FROM service WHERE service_name = '$name' LIMIT 1");
                                                    $price_per_unit = 0;
                                                    if($srv_res && $srv_res->num_rows > 0) {
                                                        $price_per_unit = (float)$srv_res->fetch_assoc()['service_price'];
                                                    }
                                                    
                                                    $row_total = $price_per_unit * $qty;
                                                    ?>
                                                    <tr>
                                                        <td class="text-center"><?= $row_idx++ ?></td>
                                                        <td><?= htmlspecialchars($name) ?></td>
                                                        <td class="text-center"><?= $qty ?></td>
                                                        <td class="text-end"><?= ($row_total > 0) ? number_format($row_total, 2) : '<span class="text-muted">หักโควตา</span>' ?></td>
                                                    </tr>
                                                    <?php
                                                }
                                            } else {
                                                // กรณีไม่มีรูปแบบ ( ) เลย ให้โชว์ทั้งก้อน
                                                ?>
                                                <tr>
                                                    <td class="text-center"><?= $row_idx++ ?></td>
                                                    <td><?= nl2br(htmlspecialchars($order_note)) ?></td>
                                                    <td class="text-center">-</td>
                                                    <td class="text-end">-</td>
                                                </tr>
                                                <?php
                                            }
                                        }
                                        ?>
                                        <!-- แถวว่างเพื่อความสวยงาม -->
                                        <tr><td colspan="4" style="height: 40px;"></td></tr>
                                    </tbody>
                                    <tfoot class="small">
                                        <?php 
                                        $shipping = (float)($o['order_shipping_fee'] ?? 0);
                                        $extra = (float)($o['order_extra_cash'] ?? 0);
                                        $subtotal = (float)$o['order_sum'] - $shipping - $extra;
                                        ?>
                                        <tr>
                                            <td colspan="3" class="text-end fw-bold">รวมยอดบริการ / Subtotal:</td>
                                            <td class="text-end fw-bold">฿<?= number_format($subtotal, 2) ?></td>
                                        </tr>
                                        <?php if($extra > 0): ?>
                                        <tr>
                                            <td colspan="3" class="text-end fw-bold text-danger">บวกเพิ่ม (<?= htmlspecialchars($o['order_extra_note']) ?>):</td>
                                            <td class="text-end fw-bold text-danger">฿<?= number_format($extra, 2) ?></td>
                                        </tr>
                                        <?php endif; ?>
                                        <?php if($shipping > 0): ?>
                                        <tr>
                                            <td colspan="3" class="text-end fw-bold">ค่าจัดส่ง / Shipping Fee:</td>
                                            <td class="text-end fw-bold">฿<?= number_format($shipping, 2) ?></td>
                                        </tr>
                                        <?php endif; ?>
                                        <tr class="table-dark">
                                            <td colspan="3" class="text-end fw-bold">ยอดสุทธิ / TOTAL:</td>
                                            <td class="text-end fw-bold fs-6">฿<?= number_format($o['order_sum'], 2) ?></td>
                                        </tr>
                                    </tfoot>
                                </table>

                                <div class="row mt-5 text-center small">
                                    <div class="col-6">
                                        <div style="height: 60px;"></div>
                                        <div class="border-bottom mx-auto" style="width: 150px;"></div>
                                        <div class="mt-2">ลงชื่อผู้รับผ้า</div>
                                    </div>
                                    <div class="col-6">
                                        <div style="height: 40px; color: rgba(220, 53, 69, 0.3); font-size: 2rem; transform: rotate(-15deg); font-weight: bold;">PAID</div>
                                        <div class="border-bottom mx-auto" style="width: 150px;"></div>
                                        <div class="mt-2">ลงชื่อผู้รับเงิน / ผู้ดำเนินการ</div>
                                    </div>
                                </div>
                            </div>

                            <div class="text-center mt-4 no-print">
                                <button class="btn btn-dark rounded-pill px-5 fw-bold shadow-sm" onclick="printOfficialReceipt('print-area-<?= $o_id ?>')">
                                    <i class="fa fa-print me-2"></i> พิมพ์ใบเสร็จ
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="p-5 text-center text-muted small">ยังไม่มีประวัติการสั่งซื้อสำหรับลูกค้าท่านนี้</div>
            <?php endif; ?>
        </div>
    </div>
</div>