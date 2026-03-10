<table style="width: 100%; margin-bottom: 10px; border-collapse: collapse; border-spacing: 0;">
  <tbody>
    <tr>
      <td style="width: 35%; border: 0; padding: 0; vertical-align: top;">
        <img src="?entryPoint=attachment&amp;id=694e19197dcc35fe0" style="display: block; max-width: 100%; max-height: 80px; width: auto;">
      </td>
      <td style="width: 65%; border: 0; padding: 0; vertical-align: top; text-align: right;">
        <p style="margin: 0;">
          <span style="font-size: 20px;"><strong>{{account.name}}</strong></span><br>
          <i>
            {{#if account.billingAddressStreet}}
            {{account.billingAddressStreet}}<br>
            {{account.billingAddressPostalCode}} {{account.billingAddressCity}}, {{account.billingAddressCountry}}<br>
            {{/if}}
            {{#if account.phoneNumber}}
            Điện thoại: {{account.phoneNumber}}<br>
            {{/if}}
            {{#if account.emailAddress}}
            Email: {{account.emailAddress}}<br>
            {{/if}}
          </i>
        </p>
      </td>
    </tr>
  </tbody>
</table>

<div style="text-align: center; margin: 40px 0;">
  <h1 style="margin: 0; padding: 0;">ĐƠN ĐẶT HÀNG</h1>
</div>
      
<table class="no-border" style="margin-bottom: 20px;">
  <tbody>
    <tr>
      <td class="section-label" style="border:0; padding: 2px 4px; line-height: 1.2;">Số phiếu:</td>
      <td>{{name}}</td>
      <td class="section-label" style="border:0; padding: 2px 4px; line-height: 1.2;"">Ngày:</td>
      <td>{{orderDate}}</td>
    </tr>
    <tr>
      <td class="section-label" style="border:0; padding: 2px 4px; line-height: 1.2;">Tỷ giá:</td>
      <td>{{exchangeRate}}</td>
      <td class="section-label" style="border:0; padding: 2px 4px; line-height: 1.2;">Tổng số lượng:</td>
      <td>{{totalQuantity}}</td>
    </tr>
  </tbody>
</table>
    
<table>
  <thead>
    <tr>
      <th class="center" style="width: 6%;">Số</th>
      <th class="center" style="width: 14%;">Tên sản phẩm</th>
      <th class="center" style="width: 8%;">Quy cách</th>
      <th class="center" style="width: 8%;">Đơn vị</th>
      <th class="center" style="width: 10%;">Số lượng</th>
      <th class="center" style="width: 16%;">Đơn giá (CNY)</th>
      <th class="center" style="width: 16%;">Thành tiền (CNY)</th>
    </tr>
  </thead>
  <tbody class="order-items">
    {{#each orderItems}}
    <tr>
      <td class="center">{{@index}}</td>
      <td><strong>{{product.name}}</strong></td>
      <td><i>{{#if product.specification}}{{product.specification}}{{else}}-{{/if}}</i></td>
      <td class="center">{{product.unit}}</td>
      <td class="center">{{quantity}}</td>
      <td class="right">{{unitPriceCny}}</td>
      <td class="right">{{totalPriceCny}}</td>
    </tr>
    {{/each}}
    </tbody>
</table>

{{#if notes}}
<div style="margin: 20px 0;">
  <p><strong>Ghi chú:</strong></p>
  <p>{{notes}}</p>
</div>
{{/if}}


<table style="margin: 20px 0;">
  <tbody>
    <tr>
      <td style="width: 40%; border:0;"></td>
      <td style="border:0;">
        <table style="border-collapse: collapse; border-spacing: 0; margin: 0 0 30px 0; width: 100%;">
          <tbody>
            <tr>
              <td class="section-label" style="border:0; padding: 2px 4px; line-height: 1.2;">Tổng cộng:</td>
              <td class="right" style="border:0; padding: 2px 4px; line-height: 1.2;">{{totalItemsPriceCny}} ¥</td>
            </tr>
            <tr>
              <td class="section-label" style="border:0; padding: 2px 4px; line-height: 1.2;">Phí vận chuyển nội địa (TQ):</td>
              <td class="right" style="border:0; padding: 2px 4px; line-height: 1.2;">{{domesticShippingFeeCny}} ¥</td>
            </tr>
            <tr>
              <td class="section-label" style="border:0; padding: 2px 4px; line-height: 1.2;">Giá trị đơn hàng:</td>
              <td class="right" style="border:0; padding: 2px 4px; line-height: 1.2;">{{totalOrderValueCny}} ¥</td>
            </tr>
          </tbody>
        </table>
        <table style="border-collapse: collapse; border-spacing: 0; margin: 0 0 30px 0; width: 100%;">
          <tbody>
            <tr>
              <td class="section-label" style="border:0; padding: 2px 4px; line-height: 1.2;">Giá trị đơn hàng quy đổi:</td>
              <td class="right" style="border:0; padding: 2px 4px; line-height: 1.2;">{{totalOrderValueVnd}} ₫</td>
            </tr>
            <tr>
              <td class="section-label" style="border:0; padding: 2px 4px; line-height: 1.2;">Phí dịch vụ:</td>
              <td class="right" style="border:0; padding: 2px 4px; line-height: 1.2;">{{serviceFeeVnd}} ₫</td>
            </tr>
            <tr>
              <td class="section-label" style="border:0; padding: 2px 4px; line-height: 1.2;">Phí vận chuyển TQ → VN (ước tính):</td>
              <td class="right" style="border:0; padding: 2px 4px; line-height: 1.2;">{{estimatedChinaVietnamShippingFeeTotalPriceVnd}} ₫</td>
            </tr>
            <tr>
              <td class="section-label" style="border:0; padding: 2px 4px; line-height: 1.2;">Số tiền phụ thu:</td>
              <td class="right" style="border:0; padding: 2px 4px; line-height: 1.2;">{{additionalAmountVnd}} ₫</td>
            </tr>
            <tr>
              <td class="section-label" style="border:0; padding: 2px 4px; line-height: 1.2;">Tổng cộng cuối:</td>
              <td class="right" style="border:0; padding: 2px 4px; line-height: 1.2; font-weight:bold;">{{grandTotalPriceVnd}} ₫</td>
            </tr>
          </tbody>
        </table>

        <table style="border-collapse: collapse; border-spacing: 0; margin: 0 0 30px 0; width: 100%;">
          <tbody>
            <tr>
              <td class="section-label" style="border:0; padding: 2px 4px; line-height: 1.2;">Số tiền đặt cọc:</td>
              <td class="right" style="border:0; padding: 2px 4px; line-height: 1.2; font-weight:bold;">{{depositAmountVnd}} ₫</td>
            </tr>
            <tr>
              <td class="section-label" style="border:0; padding: 2px 4px; line-height: 1.2;">Số tiền còn lại:</td>
              <td class="right" style="border:0; padding: 2px 4px; line-height: 1.2; font-weight:bold;">{{remainingAmountVnd}} ₫</td>
            </tr>
          </tbody>
        </table>
      </td>
    </tr>
  </tbody>
</table>

<div style="font-size: 11px; line-height: 1.3; margin: 8px 0;">
  <p><strong>Điều khoản giao dịch:</strong></p>
  <ol>
    <li>Điều kiện thanh toán: Tạm ứng 100% giá trị đơn hàng khi đặt hàng.</li>
    <li>Thời gian giao hàng: Dự kiến 20 ngày kể từ ngày nhận được tiền tạm ứng.</li>
    <li>Giao hàng tại kho bên Mua ở Hà Nội.</li>
  </ol>
</div>

<div style="font-size: 11px; line-height: 1.3; margin: 8px 0;">
  <p><strong>Lưu ý:</strong></p>
  <ol>
    <li><strong>Đối với khách nhận hàng trực tiếp tại kho:</strong>
    Vui lòng kiểm tra đầy đủ số lượng kiện hàng theo phiếu xuất kho trước khi ký nhận. Sau khi quý khách đã ký nhận, Chúng tôi sẽ không tiếp nhận khiếu nại phát sinh liên quan đến hàng hóa gì thêm.</li>
    <li><strong>Đối với hàng hóa gửi qua chuyển phát nhanh, nhà xe,...:</strong> Quý khách vui lòng quay video quá trình mở kiện hàng và kiểm tra mã vận đơn theo phiếu xuất kho. Trong trường hợp không có video đối soát, Chúng tôi không thể xử lý các vấn đề phát sinh (thiếu, nhầm, hư hỏng...).</li>
    <li><strong>Thời gian tiếp nhận phản hồi:</strong> Mọi vấn đề liên quan đến đơn hàng vui lòng phản hồi trong vòng 24 giờ kể từ khi nhận hàng. Sau thời gian này, Chúng tôi sẽ mặc định đơn hàng đã được giao đầy đủ và không có vấn đề gì phát sinh.</li>
  </ol>
  <br>
  Rất mong nhận được sự phối hợp từ quý khách hàng để việc kiểm soát hàng hóa diễn ra thuận lợi và hiệu quả hơn. Trân trọng!
</div>

<p style="margin: 40px 0 0 40px;"><strong>Bên mua</strong><br>(Ký, ghi rõ họ tên)</p>
